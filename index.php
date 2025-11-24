<?php
require_once __DIR__ . '/config/bootstrap.php';

require_once __DIR__ . '/backup/scheduler.php';
maybeRunWeeklyBackup();

$empresaInfo = getEmpresaInfo();
$empresaUrl = $empresaInfo['pagina_web'] ?? '';
$empresaUrl = $empresaUrl && filter_var($empresaUrl, FILTER_VALIDATE_URL) ? $empresaUrl : '';
$logoFile = __DIR__ . '/logo/logo.jpg';
$faviconHref = file_exists($logoFile) ? 'logo/logo.jpg?v=' . filemtime($logoFile) : 'logo/logo.jpg';

$conn = getDbConnection();
$updateConfig = loadUpdateConfig();
$updateFeedUrl = trim($updateConfig['feed_url'] ?? '');
$updateCurrentVersion = trim($updateConfig['current_version'] ?? '0.0.0');

// Esporàdics amb baixa propera
$sqlEsporadics = "SELECT Nom, Telefon1, Telefon2, Telefon3, email, DATEDIFF(Baixa, CURDATE()) AS Dies_Fins_Baixa
                  FROM esporadics
                  WHERE Baixa >= CURDATE()
                  ORDER BY Dies_Fins_Baixa ASC
                  LIMIT 100";
$sociosEsporadics = [];
if ($resultEsporadics = $conn->query($sqlEsporadics)) {
    while ($row = $resultEsporadics->fetch_assoc()) {
        $sociosEsporadics[] = $row;
    }
}

// Aniversaris
$sociosCumpleanos = [];
$sqlCumpleanos = "SELECT Nom, Telefon1, Telefon2, Telefon3, email, Activitats,
                         DATEDIFF(
                             IF(
                                 DATE(CONCAT(YEAR(CURDATE()), '-', MONTH(Data_naixement), '-', DAY(Data_naixement))) >= CURDATE(),
                                  DATE(CONCAT(YEAR(CURDATE()), '-', MONTH(Data_naixement), '-', DAY(Data_naixement))),
                                  DATE(CONCAT(YEAR(CURDATE() + INTERVAL 1 YEAR), '-', MONTH(Data_naixement), '-', DAY(Data_naixement)))
                             ),
                             CURDATE()
                         ) AS Dies_Fins_Aniversari
                  FROM socis
                  HAVING Dies_Fins_Aniversari >= 0
                  ORDER BY Dies_Fins_Aniversari ASC
                  LIMIT 100";
$noCumpleanosMessage = "";
if ($resultCumpleanos = $conn->query($sqlCumpleanos)) {
    if ($resultCumpleanos->num_rows > 0) {
        while ($row = $resultCumpleanos->fetch_assoc()) {
            $sociosCumpleanos[] = $row;
        }
    } else {
        $noCumpleanosMessage = "No hi ha socis amb aniversaris pròxims.";
    }
} else {
    $noCumpleanosMessage = "Error en la consulta d'aniversaris.";
}

// Resums
$summarySocisSql = "
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN Activitats != '' THEN 1 ELSE 0 END) AS actius,
        SUM(CASE WHEN Activitats != '' THEN Quantitat ELSE 0 END) AS total_quantitat
    FROM socis
";
$summarySocis = $conn->query($summarySocisSql)->fetch_assoc();
$totalSocis = (int)($summarySocis['total'] ?? 0);
$socisActius = (int)($summarySocis['actius'] ?? 0);
$totalQuantitatSocis = (float)($summarySocis['total_quantitat'] ?? 0);

$summaryEsporadics = $conn->query("SELECT SUM(Quantitat) AS total FROM esporadics WHERE Baixa >= CURDATE()");
$totalQuantitatEsporadics = (float)($summaryEsporadics->fetch_assoc()['total'] ?? 0);
$totalQuantitat = $totalQuantitatSocis + $totalQuantitatEsporadics;

$conn->close();
?>

<!DOCTYPE html>
<html lang="ca">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestió Gimnas</title>
    <link rel="icon" type="image/jpeg" href="<?php echo htmlspecialchars($faviconHref, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
    :root {
        --bg: #f5f6fb;
        --card: #ffffff;
        --accent: #e63946;
        --accent-dark: #c92c3c;
        --text: #1f2933;
        --muted: #6b7280;
        --border: #e5e7eb;
    }
    * { box-sizing: border-box; }
    body {
        margin: 0;
        font-family: 'Poppins', Arial, sans-serif;
        background: radial-gradient(circle at 20% 20%, #fdf1f2, #f5f6fb 40%), #f5f6fb;
        color: var(--text);
        display: flex;
    }
    .window-layer {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        pointer-events: none;
        z-index: 999;
    }
    .floating-window {
        position: absolute;
        width: 70%;
        max-width: 900px;
        height: 70%;
        min-height: 320px;
        border-radius: 16px;
        border: 1px solid rgba(15,23,42,0.15);
        box-shadow: 0 20px 40px rgba(15,23,42,0.25);
        background: #fff;
        overflow: hidden;
        pointer-events: auto;
        display: flex;
        flex-direction: column;
    }
    .floating-window header {
        background: #0f172a;
        color: #fff;
        padding: 10px 14px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-weight: 600;
        cursor: move;
    }
    .floating-window header .controls {
        display: flex;
        gap: 8px;
    }
    .floating-window header button {
        background: transparent;
        border: none;
        color: #fff;
        cursor: pointer;
        font-size: 16px;
    }
    .floating-window.minimized {
        display: none;
    }
    .minimized-dock {
        position: fixed;
        left: 260px;
        bottom: 20px;
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        z-index: 1100;
    }
    .minimized-chip {
        background: #fff;
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 8px 12px;
        cursor: pointer;
        box-shadow: 0 6px 16px rgba(15,23,42,0.1);
        font-weight: 600;
        color: var(--text);
    }
    .floating-window iframe {
        flex: 1;
        border: none;
        width: 100%;
    }
    .sidebar {
        width: 240px;
        min-height: 100vh;
        background: linear-gradient(180deg, #0f172a, #111827);
        color: #fff;
        padding: 24px 18px;
        box-shadow: 6px 0 18px rgba(0,0,0,0.12);
        position: sticky;
        top: 0;
    }
    .brand { display: flex; align-items: center; margin-bottom: 22px; }
    .brand a { display: inline-flex; }
    .brand a img { display: block; }
    .brand img {
        width: 220px;
        height: auto;
        object-fit: contain;
        border-radius: 12px;
        box-shadow: 0 6px 12px rgba(0,0,0,0.25);
        background: #fff;
        padding: 6px;
    }
    .nav-group { margin-top: 14px; }
    .nav-title {
        font-size: 13px;
        text-transform: uppercase;
        color: rgba(255,255,255,0.6);
        margin: 12px 0 8px;
        letter-spacing: 0.6px;
    }
    .dropdown { margin-bottom: 12px; }
    .dropbtn {
        width: 100%;
        text-align: left;
        cursor: pointer;
        padding: 10px 12px;
        border: 1px solid rgba(255,255,255,0.08);
        border-radius: 10px;
        background: rgba(255,255,255,0.06);
        color: #fff;
        font-weight: 500;
    }
    .dropdown-content {
        display: none;
        background: rgba(255,255,255,0.03);
        border: 1px solid rgba(255,255,255,0.07);
        border-radius: 10px;
        margin-top: 6px;
        overflow: hidden;
    }
    .dropdown.open .dropdown-content {
        display: block;
    }
    .dropdown-content a {
        display: block;
        padding: 10px 12px;
        text-decoration: none;
        color: #e5e7eb;
        font-weight: 500;
        border-bottom: 1px solid rgba(255,255,255,0.04);
    }
    .dropdown-content a:last-child { border-bottom: none; }
    .dropdown-content a:hover { background: rgba(255,255,255,0.06); color: #fff; }

    .page {
        flex: 1;
        max-width: 1200px;
        margin: 26px auto;
        padding: 0 20px 40px;
    }
    .summary-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit,minmax(220px,1fr));
        gap: 16px;
        margin: 0 0 24px;
    }
    .card {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 14px;
        padding: 16px 18px;
        box-shadow: 0 10px 24px rgba(15,23,42,0.06);
    }
    .card h3 {
        margin: 0 0 6px;
        font-size: 15px;
        color: var(--muted);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .card .value { font-size: 24px; font-weight: 700; color: var(--accent-dark); }
    .panel {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 16px;
        padding: 18px;
        box-shadow: 0 12px 26px rgba(15,23,42,0.07);
        margin-bottom: 20px;
    }
    .panel h2 { margin: 0 0 12px; font-size: 18px; color: var(--text); }
    .panel h2.section-title {
        text-align: center;
        color: var(--accent-dark);
    }
    .scrollable {
        max-height: 380px;
        overflow-y: auto;
        border: 1px solid var(--border);
        border-radius: 12px;
    }
    .scrollable table {
        width: 100%;
        border-collapse: collapse;
    }
    .scrollable thead th {
        position: sticky;
        top: 0;
        background: var(--card);
        z-index: 1;
    }
    table { width: 100%; border-collapse: collapse; font-size: 14px; }
    th, td {
        padding: 10px 12px;
        text-align: left;
        border-bottom: 1px solid var(--border);
        white-space: normal;
        word-break: break-word;
    }
    th { background: #f9fafb; font-weight: 600; color: var(--muted); }
    tr:hover td { background: #fdf2f3; }
    .btn {
        padding: 11px 14px;
        border-radius: 10px;
        border: none;
        cursor: pointer;
        font-weight: 600;
        font-size: 14px;
        background: var(--accent);
        color: #fff;
        box-shadow: 0 10px 20px rgba(230,57,70,0.18);
        width: 100%;
        margin-top: 6px;
    }
    .status { margin: 6px 0 8px; font-weight: 600; font-size: 13px; }
    .status.ok { color: #10b981; }
    .status.error { color: #ef4444; }
    @media (max-width: 768px) {
        body { flex-direction: column; }
        .sidebar { width: 100%; position: relative; min-height: auto; }
        .page { margin: 14px auto; }
    }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="brand">
        <?php if ($empresaUrl): ?>
            <a href="<?php echo htmlspecialchars($empresaUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">
                <img src="logo/logo.jpg" alt="Logo">
            </a>
        <?php else: ?>
            <img src="logo/logo.jpg" alt="Logo">
        <?php endif; ?>
    </div>
    <div class="nav-group">
        <div class="nav-title">Menú</div>
        <div class="dropdown">
            <button class="dropbtn">Arxiu</button>
            <div class="dropdown-content">
                <a href="javascript:void(0);" onclick="openWindow('arxiu/empresa.php','Arxiu - Empresa')">Empresa</a>
                <a href="javascript:void(0);" onclick="openWindow('arxiu/backup.php','Arxiu - Còpies')">Còpies de seguretat</a>
                <a href="javascript:void(0);" onclick="openWindow('arxiu/licencia.php','Arxiu - Llicència')">Llicència</a>
            </div>
        </div>
        <div class="dropdown">
            <button class="dropbtn">Activitats</button>
            <div class="dropdown-content">
                <a href="javascript:void(0);" onclick="openWindow('llistat/activitats.php','Activitats - Afegir/Eliminar')">Afegir/Eliminar</a>
                <a href="javascript:void(0);" onclick="openWindow('llistat/llistat.php','Activitats - Llistat PDF')">Llistat PDF</a>
                <a href="javascript:void(0);" onclick="openWindow('llistat/llistat_excel.php','Activitats - Llistat Excel')">Llistat Excel</a>
            </div>
        </div>
        <div class="dropdown">
            <button class="dropbtn">Socis</button>
            <div class="dropdown-content">
                <a href="javascript:void(0);" onclick="openWindow('socis/insertar.php','Socis - Afegir')">Afegir</a>
                <a href="javascript:void(0);" onclick="openWindow('socis/filtro.php','Socis - Modificar')">Modificar</a>
                <a href="javascript:void(0);" onclick="openWindow('socis/fitxa.php','Socis - Fitxa')">Fitxa</a>
                <a href="javascript:void(0);" onclick="openWindow('llistat/llistatsocis.php','Socis - Llistat')">Llistat</a>
                <a href="javascript:void(0);" onclick="openWindow('socis/baixes.php','Socis - Baixes')">Baixes</a>
            </div>
        </div>
        <div class="dropdown">
            <button class="dropbtn">Esporàdics</button>
            <div class="dropdown-content">
                <a href="javascript:void(0);" onclick="openWindow('esporadics/insertar.php','Esporadics - Afegir')">Afegir</a>
                <a href="javascript:void(0);" onclick="openWindow('esporadics/filtro.php','Esporadics - Modificar')">Modificar</a>
                <a href="javascript:void(0);" onclick="openWindow('esporadics/fitxa.php','Esporadics - Fitxa')">Fitxa</a>
                <a href="javascript:void(0);" onclick="openWindow('esporadics/llistatsocis.php','Esporadics - Llistat')">Llistat</a>
                <a href="javascript:void(0);" onclick="openWindow('esporadics/importar_contabilitat.php','Esporadics - Contabilitat')">Contabilitat</a>
            </div>
        </div>
    </div>
</div>

<div class="page">
    <div class="summary-grid">
        <div class="card">
            <h3>Total de Socis</h3>
            <div class="value"><?php echo $totalSocis; ?></div>
        </div>
        <div class="card">
            <h3>Socis actius</h3>
            <div class="value"><?php echo $socisActius; ?></div>
        </div>
        <div class="card">
            <h3>Quantitat Socis</h3>
            <div class="value"><?php echo $totalQuantitatSocis; ?></div>
        </div>
        <div class="card">
            <h3>Quantitat Esporàdics</h3>
            <div class="value"><?php echo $totalQuantitatEsporadics; ?></div>
        </div>
    </div>

    <div class="panel">
        <h2 class="section-title">Esporàdics</h2>
        <div class="scrollable">
            <?php if (!empty($sociosEsporadics)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Nom</th>
                            <th>Telèfon</th>
                            <th>Dies fins Baixa</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sociosEsporadics as $socio): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($socio['Nom'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($socio['Telefon1'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo $socio['Dies_Fins_Baixa']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No hi ha esporàdics amb baixes pròximes.</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="panel">
        <h2 class="section-title">Aniversaris</h2>
        <div class="scrollable">
            <?php if (!empty($sociosCumpleanos)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Nom</th>
                            <th>Telèfon</th>
                            <th>Activitats</th>
                            <th>Dies fins Aniversari</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sociosCumpleanos as $socio): ?>
                            <tr>
                                <td><?php echo $socio['Nom']; ?></td>
                                <td><?php echo $socio['Telefon1']; ?></td>
                                <td><?php echo $socio['Activitats']; ?></td>
                                <td><?php echo $socio['Dies_Fins_Aniversari']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p><?php echo $noCumpleanosMessage; ?></p>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="window-layer" id="windowLayer"></div>
<div class="minimized-dock" id="minimizedDock"></div>
<button id="updateFab" class="update-fab" type="button" style="display:none;">Actualitzar</button>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var dropdowns = document.querySelectorAll('.dropdown');
    dropdowns.forEach(function (dropdown) {
        var button = dropdown.querySelector('.dropbtn');
        if (!button) { return; }
        button.addEventListener('click', function () {
            var isOpen = dropdown.classList.contains('open');
            dropdowns.forEach(function (d) {
                d.classList.remove('open');
            });
            if (!isOpen) {
                dropdown.classList.add('open');
            }
        });
    });
    var feedUrl = "<?php echo addslashes($updateFeedUrl); ?>";
    var currentVersion = "<?php echo addslashes($updateCurrentVersion); ?>";
    if (feedUrl) {
        fetch(feedUrl, {cache:'no-store'})
            .then(function(resp){ return resp.ok ? resp.json() : null; })
            .then(function(data){
                if (!data) { return; }
                var list = Array.isArray(data.packages) ? data.packages : (Array.isArray(data.updates) ? data.updates : []);
                if (!list.length || !list[0].version) { return; }
                if (compareVersions(list[0].version, currentVersion) > 0) {
                    var fab = document.getElementById('updateFab');
                    fab.style.display = 'inline-flex';
                    fab.addEventListener('click', function(){
                        openWindow('arxiu/actualitzacions.php', 'Actualitzacions');
                    });
                }
            })
            .catch(function(){});
    }
});
function compareVersions(a, b) {
    var pa = a.split('.').map(Number);
    var pb = b.split('.').map(Number);
    var len = Math.max(pa.length, pb.length);
    for (var i = 0; i < len; i++) {
        var va = pa[i] || 0;
        var vb = pb[i] || 0;
        if (va > vb) return 1;
        if (va < vb) return -1;
    }
    return 0;
}
function openWindow(url, label) {
    windowManager.open(url, label);
}
const windowManager = (function () {
    const layer = document.getElementById('windowLayer');
    const dock = document.getElementById('minimizedDock');
    const STORAGE_KEY = 'gimnas_window_state_v1';
    let zCounter = 1000;
    let offset = 0;
    let suspendSave = false;
    const instances = new Set();
    function getInstancesByBase(baseLabel) {
        return Array.from(instances).filter(function (inst) {
            return inst.baseLabel === baseLabel;
        });
    }

    function updateLabels(baseLabel) {
        const related = getInstancesByBase(baseLabel).sort(function (a, b) {
            return a.createdAt - b.createdAt;
        });
        if (related.length <= 1) {
            related.forEach(function (inst) {
                setLabel(inst, baseLabel);
            });
            return;
        }
        related.forEach(function (inst, index) {
            setLabel(inst, baseLabel + ' #' + (index + 1));
        });
    }

    function setLabel(instance, text) {
        instance.displayLabel = text;
        instance.titleEl.textContent = text;
        if (instance.minimizedChip) {
            instance.minimizedChip.textContent = text;
        }
    }

    function generateId() {
        return 'win-' + Date.now().toString(36) + '-' + Math.floor(Math.random() * 100000);
    }

    function createWindow(url, label, config = {}) {
        const win = document.createElement('div');
        win.className = 'floating-window';
        win.style.top = config.top || (60 + offset) + 'px';
        win.style.left = config.left || (80 + offset) + 'px';
        win.style.zIndex = (++zCounter).toString();
        offset = (offset + 30) % 150;

        const header = document.createElement('header');
        const title = document.createElement('span');
        const baseLabel = config.baseLabel || label || url;
        title.textContent = baseLabel;
        header.appendChild(title);
        const controls = document.createElement('div');
        controls.className = 'controls';
        const minimizeBtn = document.createElement('button');
        minimizeBtn.innerHTML = '&#8211;';
        const closeBtn = document.createElement('button');
        closeBtn.innerHTML = '&times;';
        controls.appendChild(minimizeBtn);
        controls.appendChild(closeBtn);
        header.appendChild(controls);

        const frame = document.createElement('iframe');
        frame.src = url;

        win.appendChild(header);
        win.appendChild(frame);
        win.addEventListener('mousedown', function () {
            focusWindow(win);
        });
        layer.appendChild(win);

        const instance = {
            id: config.id || generateId(),
            element: win,
            iframe: frame,
            minimized: false,
            width: config.width || win.offsetWidth + 'px',
            height: config.height || win.offsetHeight + 'px',
            url,
            minimizeBtn,
            displayLabel: baseLabel,
            baseLabel,
            minimizedChip: null,
            formData: config.formData || {},
            titleEl: title,
            createdAt: config.createdAt || Date.now()
        };
        win.style.width = instance.width;
        win.style.height = instance.height;
        enableDragging(instance, header);
        setupFormPersistence(instance);

        minimizeBtn.addEventListener('click', function (event) {
            event.stopPropagation();
            toggleMinimize(instance);
        });
        closeBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            layer.removeChild(win);
            instances.delete(instance);
            removeChip(instance);
            saveState();
            updateLabels(instance.baseLabel);
        });
        instances.add(instance);
        updateLabels(instance.baseLabel);
        if (config.minimized) {
            toggleMinimize(instance, true);
        }
        saveState();
        return instance;
    }

    function enableDragging(instance, header) {
        let dragData = null;
        header.addEventListener('mousedown', function (event) {
            if (event.target.tagName === 'BUTTON' || instance.minimized) {
                return;
            }
            dragData = {
                offsetX: event.clientX - instance.element.getBoundingClientRect().left,
                offsetY: event.clientY - instance.element.getBoundingClientRect().top
            };
            document.addEventListener('mousemove', onDrag);
            document.addEventListener('mouseup', stopDrag);
        });
        function onDrag(event) {
            if (!dragData) { return; }
            const x = event.clientX - dragData.offsetX;
            const y = event.clientY - dragData.offsetY;
            instance.element.style.left = Math.max(0, Math.min(window.innerWidth - instance.element.offsetWidth, x)) + 'px';
            instance.element.style.top = Math.max(0, Math.min(window.innerHeight - instance.element.offsetHeight, y)) + 'px';
            instance.element.style.bottom = '';
            focusWindow(instance.element);
        }
        function stopDrag() {
            dragData = null;
            document.removeEventListener('mousemove', onDrag);
            document.removeEventListener('mouseup', stopDrag);
            saveState();
        }
    }

    function setupFormPersistence(instance) {
        function bind() {
            let doc;
            try {
                doc = instance.iframe.contentDocument;
            } catch (err) {
                return;
            }
            if (!doc) { return; }
            const fields = doc.querySelectorAll('input, textarea, select');
            fields.forEach(function (field) {
                const key = getFieldKey(field);
                if (!key) { return; }
                if (instance.formData && Object.prototype.hasOwnProperty.call(instance.formData, key)) {
                    applyFieldValue(field, instance.formData[key]);
                }
                const eventName = (field.tagName === 'SELECT' || field.type === 'checkbox' || field.type === 'radio') ? 'change' : 'input';
                field.addEventListener(eventName, function () {
                    instance.formData = instance.formData || {};
                    instance.formData[key] = readFieldValue(field);
                    saveState();
                });
            });
        }
        instance.iframe.addEventListener('load', function () {
            bind();
        });
        try {
            if (instance.iframe.contentDocument && instance.iframe.contentDocument.readyState === 'complete') {
                bind();
            }
        } catch (err) {}
    }

    function getFieldKey(field) {
        const base = field.name || field.id;
        if (!base) { return null; }
        if (field.type === 'checkbox' || field.type === 'radio') {
            return base + '::' + (field.value || '');
        }
        return base;
    }

    function readFieldValue(field) {
        if (field.type === 'checkbox' || field.type === 'radio') {
            return field.checked;
        }
        if (field.tagName === 'SELECT' && field.multiple) {
            return Array.from(field.options).filter(function (opt) { return opt.selected; }).map(function (opt) { return opt.value; });
        }
        return field.value;
    }

    function applyFieldValue(field, value) {
        if (field.type === 'checkbox' || field.type === 'radio') {
            field.checked = Boolean(value);
            return;
        }
        if (field.tagName === 'SELECT' && field.multiple && Array.isArray(value)) {
            Array.from(field.options).forEach(function (opt) {
                opt.selected = value.indexOf(opt.value) !== -1;
            });
            return;
        }
        if (typeof value !== 'undefined') {
            field.value = value;
        }
    }

    function toggleMinimize(instance, skipSave) {
        if (!instance) { return; }
        if (instance.minimized) {
            instance.element.classList.remove('minimized');
            instance.element.style.width = instance.width;
            instance.element.style.height = instance.height;
            instance.iframe.style.display = 'block';
            instance.minimized = false;
            instance.minimizeBtn.innerHTML = '&#8211;';
            instance.element.style.display = 'flex';
            removeChip(instance);
        } else {
            instance.element.classList.add('minimized');
            instance.iframe.style.display = 'none';
            instance.minimized = true;
            instance.minimizeBtn.innerHTML = '&#9633;';
            instance.element.style.display = 'none';
            addChip(instance);
        }
        focusWindow(instance.element);
        if (!skipSave) {
            saveState();
        }
    }

    function addChip(instance) {
        if (!dock) { return; }
        if (instance.minimizedChip && dock.contains(instance.minimizedChip)) {
            return;
        }
        const chip = document.createElement('button');
        chip.className = 'minimized-chip';
        chip.textContent = instance.displayLabel;
        chip.addEventListener('click', function () {
            toggleMinimize(instance);
        });
        instance.minimizedChip = chip;
        dock.appendChild(chip);
    }

    function removeChip(instance) {
        if (instance.minimizedChip && dock && dock.contains(instance.minimizedChip)) {
            dock.removeChild(instance.minimizedChip);
        }
        instance.minimizedChip = null;
    }

    function focusWindow(win) {
        win.style.zIndex = (++zCounter).toString();
    }

    function saveState() {
        if (suspendSave || !window.localStorage) { return; }
        const payload = {
            windows: Array.from(instances).map(function (inst) {
                return {
                    id: inst.id,
                    url: inst.url,
                    baseLabel: inst.baseLabel,
                    left: inst.element.style.left,
                    top: inst.element.style.top,
                    width: inst.width,
                    height: inst.height,
                    minimized: inst.minimized,
                    formData: inst.formData || {},
                    createdAt: inst.createdAt
                };
            })
        };
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(payload));
        } catch (err) {
            console.warn('No es pot guardar estat', err);
        }
    }

    function restoreState() {
        if (!window.localStorage) { return; }
        const raw = localStorage.getItem(STORAGE_KEY);
        if (!raw) { return; }
        let data;
        try {
            data = JSON.parse(raw);
        } catch (err) {
            return;
        }
        if (!data || !Array.isArray(data.windows)) {
            return;
        }
        suspendSave = true;
        data.windows.forEach(function (item) {
            const inst = createWindow(item.url, item.baseLabel, item);
            if (item.left) { inst.element.style.left = item.left; }
            if (item.top) { inst.element.style.top = item.top; }
            inst.width = item.width || inst.width;
            inst.height = item.height || inst.height;
            inst.element.style.width = inst.width;
            inst.element.style.height = inst.height;
            if (item.minimized) {
                toggleMinimize(inst, true);
            }
        });
        suspendSave = false;
        saveState();
    }

    return {
        open: function (url, label) {
            if (!layer) {
                window.open(url, '_blank');
                return;
            }
            const instance = createWindow(url, label);
            focusWindow(instance.element);
        },
        restore: restoreState
    };
})();
windowManager.restore();

setInterval(function () {
    window.location.reload();
}, 600000); // 10 minuts
</script></script>

</body>
</html>

