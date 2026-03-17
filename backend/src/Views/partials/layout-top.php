<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title ?? 'Guvenlik Admin', ENT_QUOTES, 'UTF-8') ?></title>
    <style>
        :root{--bg:#f6f1e8;--panel:#fffdf8;--line:#d6c6ae;--text:#1f2a30;--muted:#6a6f73;--good:#237804;--warn:#ad6800;--bad:#b42318;--accent:#0f6cbd}
        *{box-sizing:border-box}body{margin:0;font-family:Segoe UI,Arial,sans-serif;background:radial-gradient(circle at top,#fff7e6,transparent 35%),linear-gradient(180deg,#f4efe7,#ebe2d4);color:var(--text)}
        .shell{max-width:1200px;margin:0 auto;padding:24px}.card{background:var(--panel);border:1px solid var(--line);border-radius:18px;padding:20px;box-shadow:0 10px 30px rgba(76,56,28,.08)}
        .grid{display:grid;gap:16px}.grid.two{grid-template-columns:1.2fr .8fr}.toolbar{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:20px}
        .pill{display:inline-block;padding:6px 10px;border-radius:999px;font-size:12px;font-weight:700}.pill.safe{background:#edf7ed;color:var(--good)}.pill.suspicious{background:#fff4e5;color:var(--warn)}.pill.malicious{background:#fdecec;color:var(--bad)}
        input,select,button,textarea{width:100%;padding:10px 12px;border-radius:12px;border:1px solid var(--line);background:#fff;color:var(--text)}textarea{min-height:92px;resize:vertical}
        button,.button{cursor:pointer;background:var(--accent);color:#fff;font-weight:700;text-decoration:none;display:inline-block;text-align:center}
        .button.secondary,button.secondary{background:#fff;color:var(--text)}
        table{width:100%;border-collapse:collapse;font-size:14px}th,td{text-align:left;padding:10px;border-bottom:1px solid #eee}th{font-size:12px;color:var(--muted);text-transform:uppercase;letter-spacing:.05em}
        .muted{color:var(--muted)}.stack{display:grid;gap:10px}.row{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
        .header h1{margin:0;font-size:30px}.header p{margin:6px 0 0;color:var(--muted)}.summary{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}
        .summary .stat{padding:14px;border:1px solid var(--line);border-radius:14px;background:#fff}.summary .stat strong{display:block;font-size:24px}
        @media (max-width:900px){.grid.two,.summary,.row{grid-template-columns:1fr}}
    </style>
</head>
<body>
<div class="shell">

