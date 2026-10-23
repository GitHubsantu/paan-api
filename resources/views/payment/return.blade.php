<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Chutur Paan</title>
    <style>body{margin:0;font-family:system-ui,sans-serif;background:#f6f4ed;color:#243224}.card{max-width:420px;margin:12vh auto;padding:32px;text-align:center}.brand{color:#167547;font-weight:700;letter-spacing:.08em}h1{font-size:26px}p{color:#68716a;line-height:1.5}a{display:block;margin-top:28px;padding:16px;border-radius:12px;background:#167547;color:#fff;text-decoration:none;font-weight:700}</style>
</head>
<body><main class="card"><div class="brand">CHUTUR PAAN</div><h1>{{ $message }}</h1><p>Payment received. You can close this window and go back to the app.</p>@if ($scheme !== '')<a id="return-link" href="{{ $scheme }}://payment-result?orderId={{ $orderId }}&amp;ok={{ $ok }}">Return to Chutur Paan</a><script>setTimeout(function(){window.location.href=document.getElementById('return-link').href;},600);</script>@endif</main></body>
</html>
