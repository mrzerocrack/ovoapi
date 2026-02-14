<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>OVOID Tester</title>
    <style>
        body {
            margin: 0;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fb;
            color: #1f2937;
        }
        .container {
            max-width: 1200px;
            margin: 24px auto;
            padding: 0 16px 40px;
        }
        .panel {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 16px;
        }
        h1, h2, h3 {
            margin: 0 0 12px;
        }
        h1 {
            font-size: 24px;
        }
        h2 {
            font-size: 20px;
        }
        h3 {
            font-size: 17px;
        }
        .muted {
            color: #6b7280;
            font-size: 13px;
        }
        .status {
            background: #ecfdf5;
            border: 1px solid #10b981;
            color: #065f46;
            border-radius: 10px;
            padding: 10px 12px;
            margin-bottom: 12px;
        }
        .error {
            background: #fef2f2;
            border: 1px solid #ef4444;
            color: #991b1b;
            border-radius: 10px;
            padding: 10px 12px;
            margin-bottom: 12px;
        }
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 12px;
        }
        details {
            border: 1px solid #d1d5db;
            border-radius: 10px;
            background: #fcfcfd;
            padding: 10px;
        }
        summary {
            cursor: pointer;
            font-weight: 600;
            margin-bottom: 8px;
        }
        form {
            display: grid;
            gap: 8px;
        }
        label {
            font-size: 13px;
            color: #374151;
        }
        input, textarea, button {
            width: 100%;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            padding: 8px 10px;
            font-size: 14px;
            box-sizing: border-box;
        }
        textarea {
            min-height: 86px;
            resize: vertical;
        }
        button {
            background: #0f766e;
            color: #fff;
            border: 0;
            cursor: pointer;
            font-weight: 600;
        }
        button.secondary {
            background: #374151;
        }
        pre {
            margin: 0;
            white-space: pre-wrap;
            word-break: break-word;
            font-size: 12px;
            background: #0b1220;
            color: #e5e7eb;
            border-radius: 10px;
            padding: 12px;
            overflow: auto;
        }
        .pill {
            display: inline-block;
            font-size: 11px;
            border-radius: 999px;
            padding: 3px 8px;
            margin-left: 6px;
            background: #e5e7eb;
            color: #1f2937;
        }
        .pill.warn {
            background: #fee2e2;
            color: #7f1d1d;
        }
        .state-list {
            margin: 0;
            padding-left: 18px;
            font-size: 13px;
        }
        .state-list li {
            margin: 4px 0;
        }
        .state-list code {
            background: #f3f4f6;
            padding: 2px 6px;
            border-radius: 6px;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>OVOID Browser Tester</h1>
    <p class="muted">Flow login: send_otp -> otp_verify -> get_auth_token. State antar request disimpan di session browser.</p>

    @if (session('status'))
        <div class="status">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="error">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <div class="panel">
        <h2>State & Guard</h2>
        <p class="muted">
            Sensitive actions:
            @if ($allowSensitiveActions)
                <strong>ENABLED</strong>
            @else
                <strong>DISABLED</strong>
            @endif
            (atur di <code>.env</code>: <code>OVOID_ALLOW_SENSITIVE_ACTIONS=true</code>)
        </p>
        <ul class="state-list">
            @forelse($maskedState as $key => $value)
                <li><strong>{{ $key }}</strong>: <code>{{ $value }}</code></li>
            @empty
                <li>Belum ada state tersimpan.</li>
            @endforelse
        </ul>
        <form method="POST" action="{{ route('ovoid.reset') }}" style="margin-top:12px; max-width: 280px;">
            @csrf
            <button type="submit" class="secondary">Reset Session State</button>
        </form>
    </div>

    @if ($result)
        <div class="panel">
            <h2>Last Result</h2>
            <p class="muted">
                Feature: <strong>{{ $result['feature'] }}</strong> |
                Method: <strong>{{ $result['method'] }}</strong> |
                Time: <strong>{{ $result['requested_at'] }}</strong>
            </p>
            <h3>Request</h3>
            <pre>{{ json_encode($result['request'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>

            <h3 style="margin-top:12px;">Response Decoded</h3>
            @if (($result['response']['decoded'] ?? null) !== null)
                <pre>{{ json_encode($result['response']['decoded'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
            @else
                <pre>null (response bukan JSON valid)</pre>
            @endif

            <h3 style="margin-top:12px;">Response Raw</h3>
            @php $raw = $result['response']['raw'] ?? null; @endphp
            @if (is_string($raw))
                <pre>{{ $raw }}</pre>
            @else
                <pre>{{ var_export($raw, true) }}</pre>
            @endif
        </div>
    @endif

    <div class="panel">
        <h2>Feature Calls</h2>
        <p class="muted">Setiap form punya input auth_token opsional untuk override state session.</p>

        <div class="grid">
            @foreach ($features as $key => $feature)
                <details @if($loop->first) open @endif>
                    <summary>
                        {{ $key }}
                        @if ($feature['requires_token'])
                            <span class="pill">needs token</span>
                        @endif
                        @if ($feature['sensitive'])
                            <span class="pill warn">sensitive</span>
                        @endif
                    </summary>
                    <p class="muted">{{ $feature['label'] }} -> {{ $feature['method'] }}</p>
                    @if (!empty($feature['description']))
                        <p class="muted">{{ $feature['description'] }}</p>
                    @endif

                    <form method="POST" action="{{ route('ovoid.execute', ['feature' => $key]) }}">
                        @csrf
                        @foreach ($feature['fields'] as $field)
                            <label for="{{ $key }}_{{ $field['name'] }}">{{ $field['label'] }}</label>
                            @if (($field['type'] ?? 'text') === 'textarea')
                                <textarea
                                    id="{{ $key }}_{{ $field['name'] }}"
                                    name="{{ $field['name'] }}"
                                    placeholder="{{ $field['placeholder'] ?? '' }}"
                                >{{ old($field['name'], $state[$field['name']] ?? ($field['default'] ?? '')) }}</textarea>
                            @else
                                <input
                                    id="{{ $key }}_{{ $field['name'] }}"
                                    type="{{ $field['type'] ?? 'text' }}"
                                    name="{{ $field['name'] }}"
                                    value="{{ old($field['name'], $state[$field['name']] ?? ($field['default'] ?? '')) }}"
                                    placeholder="{{ $field['placeholder'] ?? '' }}"
                                >
                            @endif
                        @endforeach

                        <label for="{{ $key }}_auth_token">Auth Token (opsional override)</label>
                        <input
                            id="{{ $key }}_auth_token"
                            type="text"
                            name="auth_token"
                            value="{{ old('auth_token', $state['auth_token'] ?? '') }}"
                            placeholder="kosongkan untuk pakai token dari session"
                        >

                        <button type="submit">Jalankan</button>
                    </form>
                </details>
            @endforeach
        </div>
    </div>
</div>
</body>
</html>
