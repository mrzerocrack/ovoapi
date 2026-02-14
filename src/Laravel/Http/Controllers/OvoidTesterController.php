<?php

namespace Mrzeroc\OvoApi\Laravel\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Namdevel\Ovo;
use Throwable;

class OvoidTesterController extends Controller
{
    public function index(Request $request): View
    {
        $state = $request->session()->get('ovoid', []);

        return view('ovoid-api::ovoid.tester', [
            'features' => $this->features(),
            'state' => $state,
            'maskedState' => $this->maskedState($state),
            'result' => $request->session()->get('ovoid_result'),
            'allowSensitiveActions' => (bool) config('ovoid.allow_sensitive_actions', false),
        ]);
    }

    public function execute(Request $request, string $feature): RedirectResponse
    {
        $features = $this->features();

        if (! isset($features[$feature])) {
            abort(404);
        }

        $definition = $features[$feature];

        if (($definition['sensitive'] ?? false) && ! config('ovoid.allow_sensitive_actions', false)) {
            return redirect()
                ->route('ovoid.index')
                ->withErrors([
                    'ovoid' => 'Fitur sensitif nonaktif. Set OVOID_ALLOW_SENSITIVE_ACTIONS=true di .env jika benar-benar ingin menguji transfer/pay.',
                ]);
        }

        $validated = Validator::make($request->all(), $this->rulesForFeature($definition))->validate();

        $state = $request->session()->get('ovoid', []);
        $authToken = (string) ($validated['auth_token'] ?? $state['auth_token'] ?? '');

        if (($definition['requires_token'] ?? false) && $authToken === '') {
            return redirect()
                ->route('ovoid.index')
                ->withErrors([
                    'ovoid' => 'Auth token belum tersedia. Jalankan flow login dulu (send_otp -> otp_verify -> get_auth_token).',
                ]);
        }

        $client = new Ovo($authToken === '' ? false : $authToken);
        $method = $definition['method'];

        if (! method_exists($client, $method)) {
            return redirect()
                ->route('ovoid.index')
                ->withErrors([
                    'ovoid' => "Method {$method} tidak ditemukan di library.",
                ]);
        }

        $params = $this->buildParams($definition, $validated);

        try {
            $rawResponse = $client->{$method}(...$params);
        } catch (Throwable $exception) {
            return redirect()
                ->route('ovoid.index')
                ->withErrors([
                    'ovoid' => 'Request gagal dieksekusi: '.$exception->getMessage(),
                ]);
        }

        $normalized = $this->normalizeResponse($rawResponse);
        $state = $this->updateState($state, $validated, $normalized['decoded'] ?? null);
        $request->session()->put('ovoid', $state);

        $request->session()->flash('ovoid_result', [
            'feature' => $feature,
            'method' => $method,
            'requested_at' => now()->toDateTimeString(),
            'request' => $this->maskPayload($this->requestPayloadForResult($definition, $validated)),
            'response' => $normalized,
        ]);

        return redirect()->route('ovoid.index');
    }

    public function resetState(Request $request): RedirectResponse
    {
        $request->session()->forget('ovoid');
        $request->session()->forget('ovoid_result');

        return redirect()
            ->route('ovoid.index')
            ->with('status', 'State OVOID tester berhasil di-reset.');
    }

    private function rulesForFeature(array $feature): array
    {
        $rules = [
            'auth_token' => 'nullable|string',
        ];

        foreach ($feature['fields'] as $field) {
            $rules[$field['name']] = $field['rules'];
        }

        return $rules;
    }

    private function buildParams(array $feature, array $validated): array
    {
        $params = [];

        foreach ($feature['fields'] as $field) {
            $name = $field['name'];
            $value = $validated[$name] ?? null;

            if ($value === '') {
                $value = null;
            }

            if ($value === null && array_key_exists('default', $field)) {
                $value = $field['default'];
            }

            $params[] = $value;
        }

        while ($params !== []) {
            $lastIndex = count($params) - 1;
            $lastField = $feature['fields'][$lastIndex];
            $isOptional = ! str_contains($lastField['rules'], 'required');

            if ($params[$lastIndex] === null && $isOptional && ! array_key_exists('default', $lastField)) {
                array_pop($params);
                continue;
            }

            break;
        }

        return $params;
    }

    private function normalizeResponse(mixed $raw): array
    {
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                return [
                    'raw' => $raw,
                    'decoded' => $decoded,
                    'is_json' => true,
                ];
            }

            return [
                'raw' => $raw,
                'decoded' => null,
                'is_json' => false,
            ];
        }

        return [
            'raw' => $raw,
            'decoded' => is_array($raw) ? $raw : null,
            'is_json' => is_array($raw),
        ];
    }

    private function updateState(array $state, array $validated, mixed $decoded): array
    {
        $rememberKeys = [
            'phone_number',
            'otp_ref_id',
            'otp_token',
            'auth_token',
            'merchant_id',
            'merchant_invoice',
            'checkout_id',
            'scan_id',
            'category_id',
            'product_id',
            'biller_id',
            'denomination_id',
            'customer_id',
            'order_id',
            'payment_id',
            'amount',
            'action_mark',
            'trx_id',
            'bank_code',
            'bank_number',
            'bank_name',
            'bank_account_name',
            'qrid',
        ];

        foreach ($rememberKeys as $key) {
            if (! isset($validated[$key])) {
                continue;
            }

            $value = $validated[$key];

            if ($value === null || $value === '') {
                continue;
            }

            $state[$key] = (string) $value;
        }

        if (is_array($decoded)) {
            $authToken = $this->firstFilled($decoded, [
                'auth_token',
                'token',
                'access_token',
                'auth.access_token',
                'auth.token',
                'data.auth_token',
                'data.token',
                'data.access_token',
                'data.auth.access_token',
                'data.auth.token',
                'data.auth.auth_token',
            ]);

            if ($authToken !== null) {
                $state['auth_token'] = (string) $authToken;
            }

            $otpRefId = $this->firstFilled($decoded, [
                'otp_ref_id',
                'data.otp_ref_id',
                'otp.otp_ref_id',
                'data.otp.otp_ref_id',
            ]);

            if ($otpRefId !== null) {
                $state['otp_ref_id'] = (string) $otpRefId;
            }

            $otpToken = $this->firstFilled($decoded, [
                'otp_token',
                'data.otp_token',
                'otp.otp_token',
                'data.otp.otp_token',
            ]);

            if ($otpToken !== null) {
                $state['otp_token'] = (string) $otpToken;
            }

            $trxId = $this->firstFilled($decoded, [
                'trxId',
                'transactionId',
                'data.trxId',
                'data.transactionId',
            ]);

            if ($trxId !== null) {
                $state['trx_id'] = (string) $trxId;
            }

            $checkoutId = $this->firstFilled($decoded, [
                'checkout_id',
                'data.checkout_id',
                'steps.scan.data.checkout_id',
                'steps.checkout_data.data.checkout_id',
            ]);

            if ($checkoutId !== null) {
                $state['checkout_id'] = (string) $checkoutId;
            }

            $scanId = $this->firstFilled($decoded, [
                'scan_id',
                'data.scan_id',
                'data.details.scan_id',
                'steps.scan.data.details.scan_id',
                'steps.scan.data.scan_id',
            ]);

            if ($scanId !== null) {
                $state['scan_id'] = (string) $scanId;
            }

            $merchantId = $this->firstFilled($decoded, [
                'data.orders.0.merchant_id',
                'data.orders.0.merchantId',
                'steps.checkout_process.data.orders.0.merchant_id',
                'steps.checkout_process.data.orders.0.merchantId',
            ]);

            if ($merchantId !== null) {
                $state['merchant_id'] = (string) $merchantId;
            }

            $orderId = $this->firstFilled($decoded, [
                'order_id',
                'data.orders.0.order_id',
                'data.orders.0.orderId',
                'steps.checkout_process.data.orders.0.order_id',
                'steps.checkout_process.data.orders.0.orderId',
            ]);

            if ($orderId !== null) {
                $state['order_id'] = (string) $orderId;
            }

            $paymentId = $this->firstFilled($decoded, [
                'payment_id',
                'data.orders.0.payment_id',
                'data.orders.0.paymentId',
                'steps.checkout_process.data.orders.0.payment_id',
                'steps.checkout_process.data.orders.0.paymentId',
            ]);

            if ($paymentId !== null) {
                $state['payment_id'] = (string) $paymentId;
                $state['merchant_invoice'] = (string) $paymentId;
            }
        }

        return $state;
    }

    private function firstFilled(array $payload, array $paths): mixed
    {
        foreach ($paths as $path) {
            $value = Arr::get($payload, $path);

            if ($value === null || $value === '') {
                continue;
            }

            return $value;
        }

        return null;
    }

    private function requestPayloadForResult(array $feature, array $validated): array
    {
        $keys = array_merge(
            array_column($feature['fields'], 'name'),
            ['auth_token']
        );

        return Arr::only($validated, $keys);
    }

    private function maskPayload(array $payload): array
    {
        $sensitiveKeys = [
            'auth_token',
            'security_code',
            'otp_code',
            'otp_token',
        ];

        foreach ($sensitiveKeys as $key) {
            if (isset($payload[$key])) {
                $payload[$key] = $this->maskValue($payload[$key]);
            }
        }

        return $payload;
    }

    private function maskedState(array $state): array
    {
        $masked = $state;

        foreach (['auth_token', 'otp_token'] as $key) {
            if (isset($masked[$key])) {
                $masked[$key] = $this->maskValue($masked[$key]);
            }
        }

        return $masked;
    }

    private function maskValue(mixed $value): string
    {
        $string = (string) $value;
        $length = strlen($string);

        if ($length === 0) {
            return '';
        }

        if ($length <= 6) {
            return str_repeat('*', $length);
        }

        return substr($string, 0, 3).str_repeat('*', max($length - 6, 4)).substr($string, -3);
    }

    private function features(): array
    {
        $tokenOnly = static fn (string $label, string $method, string $description = ''): array => [
            'label' => $label,
            'method' => $method,
            'description' => $description,
            'requires_token' => true,
            'sensitive' => false,
            'fields' => [],
        ];

        return [
            'send_otp' => [
                'label' => 'Send OTP',
                'method' => 'sendOtp',
                'description' => 'Langkah awal login OVO.',
                'requires_token' => false,
                'sensitive' => false,
                'fields' => [
                    ['name' => 'phone_number', 'label' => 'Phone Number', 'rules' => 'required|string|max:20', 'placeholder' => '+628xxxxxxxxxx'],
                ],
            ],
            'otp_verify' => [
                'label' => 'OTP Verify',
                'method' => 'OTPVerify',
                'description' => 'Verifikasi OTP atau otp_link_code.',
                'requires_token' => false,
                'sensitive' => false,
                'fields' => [
                    ['name' => 'phone_number', 'label' => 'Phone Number', 'rules' => 'required|string|max:20', 'placeholder' => '+628xxxxxxxxxx'],
                    ['name' => 'otp_ref_id', 'label' => 'OTP Ref ID', 'rules' => 'required|string|max:255'],
                    ['name' => 'otp_code', 'label' => 'OTP Code / OTP Link Code', 'rules' => 'required|string|max:255'],
                ],
            ],
            'get_auth_token' => [
                'label' => 'Get Auth Token',
                'method' => 'getAuthToken',
                'description' => 'Tukar otp_token + security_code jadi auth token.',
                'requires_token' => false,
                'sensitive' => false,
                'fields' => [
                    ['name' => 'phone_number', 'label' => 'Phone Number', 'rules' => 'required|string|max:20'],
                    ['name' => 'otp_ref_id', 'label' => 'OTP Ref ID', 'rules' => 'required|string|max:255'],
                    ['name' => 'otp_token', 'label' => 'OTP Token', 'rules' => 'required|string|max:255'],
                    ['name' => 'security_code', 'label' => 'Security Code / PIN', 'rules' => 'required|string|max:12'],
                ],
            ],
            'get_public_keys' => [
                'label' => 'Get Public Keys',
                'method' => 'getPublicKeys',
                'description' => 'Ambil RSA public key dari OVO.',
                'requires_token' => false,
                'sensitive' => false,
                'fields' => [],
            ],
            'get_last_transactions' => [
                'label' => 'Get Last Transactions',
                'method' => 'getLastTransactions',
                'description' => 'Mutasi singkat transfer terakhir.',
                'requires_token' => true,
                'sensitive' => false,
                'fields' => [
                    ['name' => 'limit', 'label' => 'Limit', 'rules' => 'nullable|integer|min:1|max:50', 'default' => 5],
                ],
            ],
            'get_transaction_details' => [
                'label' => 'Get Transaction Details',
                'method' => 'getTransactionDetails',
                'description' => 'Detail mutasi berdasarkan merchant_id dan merchant_invoice.',
                'requires_token' => true,
                'sensitive' => false,
                'fields' => [
                    ['name' => 'merchant_id', 'label' => 'Merchant ID', 'rules' => 'required|string|max:100'],
                    ['name' => 'merchant_invoice', 'label' => 'Merchant Invoice', 'rules' => 'required|string|max:100'],
                ],
            ],
            'get_favorite_transfer' => $tokenOnly('Get Favorite Transfer', 'getFavoriteTransfer'),
            'get_email' => $tokenOnly('Get Email', 'getEmail'),
            'transaction_history' => [
                'label' => 'Transaction History',
                'method' => 'transactionHistory',
                'description' => 'Mutasi paging (paling penting untuk cek riwayat).',
                'requires_token' => true,
                'sensitive' => false,
                'fields' => [
                    ['name' => 'page', 'label' => 'Page', 'rules' => 'nullable|integer|min:1', 'default' => 1],
                    ['name' => 'limit', 'label' => 'Limit', 'rules' => 'nullable|integer|min:1|max:100', 'default' => 10],
                ],
            ],
            'wallet_inquiry' => $tokenOnly('Wallet Inquiry', 'walletInquiry'),
            'get_ovo_cash' => $tokenOnly('Get OVO Cash', 'getOvoCash'),
            'get_ovo_cash_card_number' => $tokenOnly('Get OVO Cash Card Number', 'getOvoCashCardNumber'),
            'get_ovo_points_card_number' => $tokenOnly('Get OVO Points Card Number', 'getOvoPointsCardNumber'),
            'get_ovo_points' => $tokenOnly('Get OVO Points', 'getOvoPoints'),
            'get_point_details' => $tokenOnly('Get Point Details', 'getPointDetails'),
            'get_biller_list' => $tokenOnly('Get Biller List', 'getBillerList'),
            'get_biller_category' => [
                'label' => 'Get Biller Category',
                'method' => 'getBillerCategory',
                'description' => 'Ambil biller berdasarkan category id.',
                'requires_token' => true,
                'sensitive' => false,
                'fields' => [
                    ['name' => 'category_id', 'label' => 'Category ID', 'rules' => 'required|integer|min:0'],
                ],
            ],
            'get_denominations' => [
                'label' => 'Get Denominations',
                'method' => 'getDenominations',
                'description' => 'Ambil nominal dari product id.',
                'requires_token' => true,
                'sensitive' => false,
                'fields' => [
                    ['name' => 'product_id', 'label' => 'Product ID', 'rules' => 'required|integer|min:1'],
                ],
            ],
            'get_bank_list' => $tokenOnly('Get Bank List', 'getBankList'),
            'get_unread_notifications' => $tokenOnly('Get Unread Notifications', 'getUnreadNotifications'),
            'get_all_notifications' => $tokenOnly('Get All Notifications', 'getAllNotifications'),
            'get_investment' => $tokenOnly('Get Investment', 'getInvestment'),
            'biller_inquiry' => [
                'label' => 'Biller Inquiry',
                'method' => 'billerInquiry',
                'description' => 'Cek info pembayaran biller.',
                'requires_token' => true,
                'sensitive' => false,
                'fields' => [
                    ['name' => 'biller_id', 'label' => 'Biller ID', 'rules' => 'required|string|max:100'],
                    ['name' => 'product_id', 'label' => 'Product ID', 'rules' => 'required|string|max:100'],
                    ['name' => 'denomination_id', 'label' => 'Denomination ID', 'rules' => 'required|string|max:100'],
                    ['name' => 'customer_id', 'label' => 'Customer ID', 'rules' => 'required|string|max:100'],
                ],
            ],
            'biller_pay' => [
                'label' => 'Biller Pay',
                'method' => 'billerPay',
                'description' => 'Aksi pembayaran biller (sensitif).',
                'requires_token' => true,
                'sensitive' => true,
                'fields' => [
                    ['name' => 'biller_id', 'label' => 'Biller ID', 'rules' => 'required|string|max:100'],
                    ['name' => 'product_id', 'label' => 'Product ID', 'rules' => 'required|string|max:100'],
                    ['name' => 'order_id', 'label' => 'Order ID', 'rules' => 'required|string|max:150'],
                    ['name' => 'amount', 'label' => 'Amount', 'rules' => 'required|numeric|min:1'],
                    ['name' => 'customer_id', 'label' => 'Customer ID', 'rules' => 'required|string|max:100'],
                ],
            ],
            'is_ovo' => [
                'label' => 'Is OVO',
                'method' => 'isOVO',
                'description' => 'Validasi nomor OVO.',
                'requires_token' => true,
                'sensitive' => false,
                'fields' => [
                    ['name' => 'amount', 'label' => 'Amount', 'rules' => 'required|numeric|min:1'],
                    ['name' => 'phone_number', 'label' => 'Phone Number', 'rules' => 'required|string|max:20'],
                ],
            ],
            'generate_trx_id' => [
                'label' => 'Generate Trx ID',
                'method' => 'generateTrxId',
                'description' => 'Siapkan transaction id untuk transfer/qris/pay.',
                'requires_token' => true,
                'sensitive' => false,
                'fields' => [
                    ['name' => 'amount', 'label' => 'Amount', 'rules' => 'required|numeric|min:1'],
                    ['name' => 'action_mark', 'label' => 'Action Mark', 'rules' => 'nullable|string|max:50', 'default' => 'OVO Cash'],
                ],
            ],
            'unlock_validate_trx_id' => [
                'label' => 'Unlock And Validate Trx ID',
                'method' => 'unlockAndValidateTrxId',
                'description' => 'Validasi trx dengan PIN.',
                'requires_token' => true,
                'sensitive' => false,
                'fields' => [
                    ['name' => 'amount', 'label' => 'Amount', 'rules' => 'required|numeric|min:1'],
                    ['name' => 'trx_id', 'label' => 'Trx ID', 'rules' => 'required|string|max:150'],
                    ['name' => 'security_code', 'label' => 'Security Code / PIN', 'rules' => 'required|string|max:12'],
                ],
            ],
            'transfer_ovo' => [
                'label' => 'Transfer OVO',
                'method' => 'transferOVO',
                'description' => 'Transfer ke sesama OVO (sensitif).',
                'requires_token' => true,
                'sensitive' => true,
                'fields' => [
                    ['name' => 'amount', 'label' => 'Amount', 'rules' => 'required|numeric|min:1'],
                    ['name' => 'phone_number', 'label' => 'Phone Number Tujuan', 'rules' => 'required|string|max:20'],
                    ['name' => 'trx_id', 'label' => 'Trx ID', 'rules' => 'required|string|max:150'],
                    ['name' => 'message', 'label' => 'Message', 'rules' => 'nullable|string|max:255'],
                ],
            ],
            'transfer_bank_inquiry' => [
                'label' => 'Transfer Bank Inquiry',
                'method' => 'transferBankInquiry',
                'description' => 'Cek data rekening tujuan bank.',
                'requires_token' => true,
                'sensitive' => false,
                'fields' => [
                    ['name' => 'bank_code', 'label' => 'Bank Code', 'rules' => 'required|string|max:50'],
                    ['name' => 'bank_number', 'label' => 'Bank Number', 'rules' => 'required|string|max:50'],
                    ['name' => 'amount', 'label' => 'Amount', 'rules' => 'required|numeric|min:1'],
                    ['name' => 'message', 'label' => 'Message', 'rules' => 'nullable|string|max:255'],
                    ['name' => 'bank_name', 'label' => 'Bank Name', 'rules' => 'required|string|max:100', 'placeholder' => 'BANK CENTRAL ASIA'],
                ],
            ],
            'transfer_bank_direct' => [
                'label' => 'Transfer Bank Direct',
                'method' => 'transferBankDirect',
                'description' => 'Eksekusi transfer bank (sensitif).',
                'requires_token' => true,
                'sensitive' => true,
                'fields' => [
                    ['name' => 'bank_code', 'label' => 'Bank Code', 'rules' => 'required|string|max:50'],
                    ['name' => 'bank_number', 'label' => 'Bank Number', 'rules' => 'required|string|max:50'],
                    ['name' => 'bank_name', 'label' => 'Bank Name', 'rules' => 'required|string|max:100'],
                    ['name' => 'bank_account_name', 'label' => 'Bank Account Name', 'rules' => 'required|string|max:100'],
                    ['name' => 'trx_id', 'label' => 'Trx ID', 'rules' => 'required|string|max:150'],
                    ['name' => 'amount', 'label' => 'Amount', 'rules' => 'required|numeric|min:1'],
                    ['name' => 'notes', 'label' => 'Notes', 'rules' => 'nullable|string|max:255'],
                ],
            ],
            'qris_pay' => [
                'label' => 'QRIS Pay',
                'method' => 'QrisPay',
                'description' => 'Bayar QRIS (sensitif).',
                'requires_token' => true,
                'sensitive' => true,
                'fields' => [
                    ['name' => 'amount', 'label' => 'Amount', 'rules' => 'required|numeric|min:1'],
                    ['name' => 'trx_id', 'label' => 'Trx ID', 'rules' => 'required|string|max:150'],
                    ['name' => 'qrid', 'label' => 'QR String', 'rules' => 'required|string|max:4000', 'type' => 'textarea'],
                ],
            ],
        ];
    }
}
