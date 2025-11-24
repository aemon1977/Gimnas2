<?php਍␀猀攀爀瘀攀爀渀愀洀攀 㴀 ∀氀漀挀愀氀栀漀猀琀∀㬀ഀ
$conn = getDbConnection();
$empresa = getEmpresa($conn);਍ഀ
if ($_SERVER['REQUEST_METHOD'] === 'POST') {਍    ␀渀漀洀 㴀 琀爀椀洀⠀␀开倀伀匀吀嬀✀渀漀洀✀崀 㼀㼀 ✀✀⤀㬀ഀ
    $adresa = trim($_POST['adresa'] ?? '');਍    ␀瀀漀戀氀愀挀椀漀 㴀 琀爀椀洀⠀␀开倀伀匀吀嬀✀瀀漀戀氀愀挀椀漀✀崀 㼀㼀 ✀✀⤀㬀ഀ
    $provincia = trim($_POST['provincia'] ?? '');਍    ␀挀漀搀椀开瀀漀猀琀愀氀 㴀 琀爀椀洀⠀␀开倀伀匀吀嬀✀挀漀搀椀开瀀漀猀琀愀氀✀崀 㼀㼀 ✀✀⤀㬀ഀ
    $telefon = trim($_POST['telefon'] ?? '');਍    ␀洀漀戀椀氀 㴀 琀爀椀洀⠀␀开倀伀匀吀嬀✀洀漀戀椀氀✀崀 㼀㼀 ✀✀⤀㬀ഀ
    $email = trim($_POST['email'] ?? '');਍    ␀瀀愀最椀渀愀开眀攀戀 㴀 琀爀椀洀⠀␀开倀伀匀吀嬀✀瀀愀最椀渀愀开眀攀戀✀崀 㼀㼀 ✀✀⤀㬀ഀ
    $cif_nif = trim($_POST['cif_nif'] ?? '');਍ഀ
    $logoPath = $empresa['logo_path'] ?? null;਍    ␀甀瀀氀漀愀搀攀搀䰀漀最漀 㴀 昀愀氀猀攀㬀ഀ
਍    椀昀 ⠀℀攀洀瀀琀礀⠀␀开䘀䤀䰀䔀匀嬀✀氀漀最漀✀崀嬀✀渀愀洀攀✀崀⤀ ☀☀ ␀开䘀䤀䰀䔀匀嬀✀氀漀最漀✀崀嬀✀攀爀爀漀爀✀崀 㴀㴀㴀 唀倀䰀伀䄀䐀开䔀刀刀开伀䬀⤀ 笀ഀ
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];਍        ␀洀椀洀攀 㴀 洀椀洀攀开挀漀渀琀攀渀琀开琀礀瀀攀⠀␀开䘀䤀䰀䔀匀嬀✀氀漀最漀✀崀嬀✀琀洀瀀开渀愀洀攀✀崀⤀㬀ഀ
        if (!isset($allowed[$mime])) {਍            ␀攀爀爀漀爀䴀攀猀猀愀最攀 㴀 ✀䘀漀爀洀愀琀 搀攀 氀漀最漀 渀漀 猀甀瀀漀爀琀愀琀⸀ 唀猀愀 䨀倀䜀Ⰰ 倀一䜀 漀 圀攀戀倀⸀✀㬀ഀ
        } else {਍            ␀攀砀琀 㴀 ␀愀氀氀漀眀攀搀嬀␀洀椀洀攀崀㬀ഀ
            if (!is_dir(__DIR__ . '/../logo')) {਍                洀欀搀椀爀⠀开开䐀䤀刀开开 ⸀ ✀⼀⸀⸀⼀氀漀最漀✀Ⰰ 　㜀㜀㜀Ⰰ 琀爀甀攀⤀㬀ഀ
            }਍            ␀搀攀猀琀椀渀愀琀椀漀渀 㴀 开开䐀䤀刀开开 ⸀ ✀⼀⸀⸀⼀氀漀最漀⼀氀漀最漀⸀✀ ⸀ ␀攀砀琀㬀ഀ
            foreach (glob(__DIR__ . '/../logo/logo.*') as $existing) {਍                䀀甀渀氀椀渀欀⠀␀攀砀椀猀琀椀渀最⤀㬀ഀ
            }਍            椀昀 ⠀洀漀瘀攀开甀瀀氀漀愀搀攀搀开昀椀氀攀⠀␀开䘀䤀䰀䔀匀嬀✀氀漀最漀✀崀嬀✀琀洀瀀开渀愀洀攀✀崀Ⰰ ␀搀攀猀琀椀渀愀琀椀漀渀⤀⤀ 笀ഀ
                $logoPath = 'logo/' . basename($destination);਍                ␀甀瀀氀漀愀搀攀搀䰀漀最漀 㴀 琀爀甀攀㬀ഀ
            } else {਍                ␀攀爀爀漀爀䴀攀猀猀愀最攀 㴀 ✀一漀 猀尀✀栀愀 瀀漀最甀琀 瀀甀樀愀爀 攀氀 氀漀最漀⸀✀㬀ഀ
            }਍        紀ഀ
    }਍ഀ
    if (empty($errorMessage)) {਍        椀昀 ⠀␀攀洀瀀爀攀猀愀⤀ 笀ഀ
            $stmt = $conn->prepare("UPDATE Empresa SET nom=?, adresa=?, poblacio=?, provincia=?, codi_postal=?, telefon=?, mobil=?, email=?, pagina_web=?, cif_nif=?, logo_path=? WHERE id=?");਍            ␀椀搀 㴀 ␀攀洀瀀爀攀猀愀嬀✀椀搀✀崀㬀ഀ
            $stmt->bind_param('sssssssssssi', $nom, $adresa, $poblacio, $provincia, $codi_postal, $telefon, $mobil, $email, $pagina_web, $cif_nif, $logoPath, $id);਍            ␀猀琀洀琀ⴀ㸀攀砀攀挀甀琀攀⠀⤀㬀ഀ
            $stmt->close();਍        紀 攀氀猀攀 笀ഀ
            $stmt = $conn->prepare("INSERT INTO Empresa (nom, adresa, poblacio, provincia, codi_postal, telefon, mobil, email, pagina_web, cif_nif, logo_path) VALUES (?,?,?,?,?,?,?,?,?,?,?)");਍            ␀猀琀洀琀ⴀ㸀戀椀渀搀开瀀愀爀愀洀⠀✀猀猀猀猀猀猀猀猀猀猀猀✀Ⰰ ␀渀漀洀Ⰰ ␀愀搀爀攀猀愀Ⰰ ␀瀀漀戀氀愀挀椀漀Ⰰ ␀瀀爀漀瘀椀渀挀椀愀Ⰰ ␀挀漀搀椀开瀀漀猀琀愀氀Ⰰ ␀琀攀氀攀昀漀渀Ⰰ ␀洀漀戀椀氀Ⰰ ␀攀洀愀椀氀Ⰰ ␀瀀愀最椀渀愀开眀攀戀Ⰰ ␀挀椀昀开渀椀昀Ⰰ ␀氀漀最漀倀愀琀栀⤀㬀ഀ
            $stmt->execute();਍            ␀猀琀洀琀ⴀ㸀挀氀漀猀攀⠀⤀㬀ഀ
        }਍        ␀猀甀挀挀攀猀猀䴀攀猀猀愀最攀 㴀 ␀甀瀀氀漀愀搀攀搀䰀漀最漀 㼀 ∀䐀愀搀攀猀 椀 氀漀最漀 愀挀琀甀愀氀椀琀稀愀琀猀⸀∀ 㨀 ∀䐀愀搀攀猀 最甀愀爀搀愀搀攀猀 挀漀爀爀攀挀琀愀洀攀渀琀⸀∀㬀ഀ
        $empresa = getEmpresa($conn);਍    紀ഀ
}਍ഀ
?>਍㰀℀䐀伀䌀吀夀倀䔀 栀琀洀氀㸀ഀ
<html lang="ca">਍㰀栀攀愀搀㸀ഀ
    <meta charset="UTF-8">਍    㰀琀椀琀氀攀㸀䔀洀瀀爀攀猀愀㰀⼀琀椀琀氀攀㸀ഀ
    <link rel="preconnect" href="https://fonts.googleapis.com">਍    㰀氀椀渀欀 爀攀氀㴀∀瀀爀攀挀漀渀渀攀挀琀∀ 栀爀攀昀㴀∀栀琀琀瀀猀㨀⼀⼀昀漀渀琀猀⸀最猀琀愀琀椀挀⸀挀漀洀∀ 挀爀漀猀猀漀爀椀最椀渀㸀ഀ
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">਍    㰀猀琀礀氀攀㸀ഀ
        body {਍            昀漀渀琀ⴀ昀愀洀椀氀礀㨀 ✀倀漀瀀瀀椀渀猀✀Ⰰ 䄀爀椀愀氀Ⰰ 猀愀渀猀ⴀ猀攀爀椀昀㬀ഀ
            margin: 0;਍            瀀愀搀搀椀渀最㨀 ㈀　瀀砀㬀ഀ
            background: #f5f6fb;਍            挀漀氀漀爀㨀 ⌀㄀昀㈀㤀㌀㌀㬀ഀ
        }਍        ⸀瀀愀最攀 笀ഀ
            max-width: 900px;਍            洀愀爀最椀渀㨀 　 愀甀琀漀㬀ഀ
        }਍        栀㄀ 笀ഀ
            margin-top: 0;਍        紀ഀ
        form {਍            搀椀猀瀀氀愀礀㨀 最爀椀搀㬀ഀ
            grid-template-columns: repeat(auto-fit,minmax(240px,1fr));਍            最愀瀀㨀 ㄀㘀瀀砀㬀ഀ
            background: #fff;਍            戀漀爀搀攀爀ⴀ爀愀搀椀甀猀㨀 ㄀㘀瀀砀㬀ഀ
            padding: 20px;਍            戀漀砀ⴀ猀栀愀搀漀眀㨀 　 ㄀　瀀砀 ㌀　瀀砀 爀最戀愀⠀㄀㔀Ⰰ㈀㌀Ⰰ㐀㈀Ⰰ　⸀㄀⤀㬀ഀ
        }਍        氀愀戀攀氀 笀ഀ
            font-weight: 600;਍            搀椀猀瀀氀愀礀㨀 戀氀漀挀欀㬀ഀ
            margin-bottom: 6px;਍        紀ഀ
        input {਍            眀椀搀琀栀㨀 ㄀　　─㬀ഀ
            border: 1px solid #e5e7eb;਍            戀漀爀搀攀爀ⴀ爀愀搀椀甀猀㨀 ㄀　瀀砀㬀ഀ
            padding: 10px;਍        紀ഀ
        .full {਍            最爀椀搀ⴀ挀漀氀甀洀渀㨀 ㄀ ⼀ ⴀ㄀㬀ഀ
        }਍        ⸀愀挀琀椀漀渀猀 笀ഀ
            margin-top: 16px;਍            搀椀猀瀀氀愀礀㨀 昀氀攀砀㬀ഀ
            gap: 12px;਍        紀ഀ
        button {਍            瀀愀搀搀椀渀最㨀 ㄀㈀瀀砀 ㄀㠀瀀砀㬀ഀ
            border: none;਍            戀漀爀搀攀爀ⴀ爀愀搀椀甀猀㨀 ㄀㈀瀀砀㬀ഀ
            font-weight: 600;਍            挀甀爀猀漀爀㨀 瀀漀椀渀琀攀爀㬀ഀ
        }਍        ⸀瀀爀椀洀愀爀礀 笀 戀愀挀欀最爀漀甀渀搀㨀 ⌀攀㘀㌀㤀㐀㘀㬀 挀漀氀漀爀㨀 ⌀昀昀昀㬀 紀ഀ
        .secondary { background: #1f2933; color: #fff; }਍        ⸀愀氀攀爀琀 笀ഀ
            margin: 12px 0;਍            瀀愀搀搀椀渀最㨀 ㄀㈀瀀砀 ㄀㘀瀀砀㬀ഀ
            border-radius: 12px;਍        紀ഀ
        .alert.success { background: #ecfdf5; color: #065f46; border: 1px solid #34d399; }਍        ⸀愀氀攀爀琀⸀攀爀爀漀爀 笀 戀愀挀欀最爀漀甀渀搀㨀 ⌀昀攀昀㈀昀㈀㬀 挀漀氀漀爀㨀 ⌀㤀㤀㄀戀㄀戀㬀 戀漀爀搀攀爀㨀 ㄀瀀砀 猀漀氀椀搀 ⌀昀㠀㜀㄀㜀㄀㬀 紀ഀ
        .logo-preview {਍            洀愀爀最椀渀ⴀ琀漀瀀㨀 ㄀㘀瀀砀㬀ഀ
            display: inline-flex;਍            愀氀椀最渀ⴀ椀琀攀洀猀㨀 挀攀渀琀攀爀㬀ഀ
            gap: 12px;਍        紀ഀ
        .logo-preview img {਍            洀愀砀ⴀ栀攀椀最栀琀㨀 ㄀㈀　瀀砀㬀ഀ
            border-radius: 12px;਍            戀漀爀搀攀爀㨀 ㄀瀀砀 猀漀氀椀搀 ⌀攀㔀攀㜀攀戀㬀ഀ
            padding: 6px;਍            戀愀挀欀最爀漀甀渀搀㨀 ⌀昀昀昀㬀ഀ
        }਍    㰀⼀猀琀礀氀攀㸀ഀ
</head>਍㰀戀漀搀礀㸀ഀ
    <div class="page">਍        㰀栀㄀㸀䐀愀搀攀猀 搀攀 氀✀䔀洀瀀爀攀猀愀㰀⼀栀㄀㸀ഀ
        <?php if ($successMessage): ?><div class="alert success"><?php echo htmlspecialchars($successMessage); ?></div><?php endif; ?>਍        㰀㼀瀀栀瀀 椀昀 ⠀␀攀爀爀漀爀䴀攀猀猀愀最攀⤀㨀 㼀㸀㰀搀椀瘀 挀氀愀猀猀㴀∀愀氀攀爀琀 攀爀爀漀爀∀㸀㰀㼀瀀栀瀀 攀挀栀漀 栀琀洀氀猀瀀攀挀椀愀氀挀栀愀爀猀⠀␀攀爀爀漀爀䴀攀猀猀愀最攀⤀㬀 㼀㸀㰀⼀搀椀瘀㸀㰀㼀瀀栀瀀 攀渀搀椀昀㬀 㼀㸀ഀ
        <form method="post" enctype="multipart/form-data">਍            㰀搀椀瘀㸀ഀ
                <label for="nom">Nom</label>਍                㰀椀渀瀀甀琀 琀礀瀀攀㴀∀琀攀砀琀∀ 渀愀洀攀㴀∀渀漀洀∀ 椀搀㴀∀渀漀洀∀ 瘀愀氀甀攀㴀∀㰀㼀瀀栀瀀 攀挀栀漀 栀琀洀氀猀瀀攀挀椀愀氀挀栀愀爀猀⠀␀攀洀瀀爀攀猀愀嬀✀渀漀洀✀崀 㼀㼀 ✀✀⤀㬀 㼀㸀∀㸀ഀ
            </div>਍            㰀搀椀瘀㸀ഀ
                <label for="adresa">Adreça</label>਍                㰀椀渀瀀甀琀 琀礀瀀攀㴀∀琀攀砀琀∀ 渀愀洀攀㴀∀愀搀爀攀猀愀∀ 椀搀㴀∀愀搀爀攀猀愀∀ 瘀愀氀甀攀㴀∀㰀㼀瀀栀瀀 攀挀栀漀 栀琀洀氀猀瀀攀挀椀愀氀挀栀愀爀猀⠀␀攀洀瀀爀攀猀愀嬀✀愀搀爀攀猀愀✀崀 㼀㼀 ✀✀⤀㬀 㼀㸀∀㸀ഀ
            </div>਍            㰀搀椀瘀㸀ഀ
                <label for="poblacio">Població</label>਍                㰀椀渀瀀甀琀 琀礀瀀攀㴀∀琀攀砀琀∀ 渀愀洀攀㴀∀瀀漀戀氀愀挀椀漀∀ 椀搀㴀∀瀀漀戀氀愀挀椀漀∀ 瘀愀氀甀攀㴀∀㰀㼀瀀栀瀀 攀挀栀漀 栀琀洀氀猀瀀攀挀椀愀氀挀栀愀爀猀⠀␀攀洀瀀爀攀猀愀嬀✀瀀漀戀氀愀挀椀漀✀崀 㼀㼀 ✀✀⤀㬀 㼀㸀∀㸀ഀ
            </div>਍            㰀搀椀瘀㸀ഀ
                <label for="provincia">Província</label>਍                㰀椀渀瀀甀琀 琀礀瀀攀㴀∀琀攀砀琀∀ 渀愀洀攀㴀∀瀀爀漀瘀椀渀挀椀愀∀ 椀搀㴀∀瀀爀漀瘀椀渀挀椀愀∀ 瘀愀氀甀攀㴀∀㰀㼀瀀栀瀀 攀挀栀漀 栀琀洀氀猀瀀攀挀椀愀氀挀栀愀爀猀⠀␀攀洀瀀爀攀猀愀嬀✀瀀爀漀瘀椀渀挀椀愀✀崀 㼀㼀 ✀✀⤀㬀 㼀㸀∀㸀ഀ
            </div>਍            㰀搀椀瘀㸀ഀ
                <label for="codi_postal">Codi Postal</label>਍                㰀椀渀瀀甀琀 琀礀瀀攀㴀∀琀攀砀琀∀ 渀愀洀攀㴀∀挀漀搀椀开瀀漀猀琀愀氀∀ 椀搀㴀∀挀漀搀椀开瀀漀猀琀愀氀∀ 瘀愀氀甀攀㴀∀㰀㼀瀀栀瀀 攀挀栀漀 栀琀洀氀猀瀀攀挀椀愀氀挀栀愀爀猀⠀␀攀洀瀀爀攀猀愀嬀✀挀漀搀椀开瀀漀猀琀愀氀✀崀 㼀㼀 ✀✀⤀㬀 㼀㸀∀㸀ഀ
            </div>਍            㰀搀椀瘀㸀ഀ
                <label for="telefon">Telèfon</label>਍                㰀椀渀瀀甀琀 琀礀瀀攀㴀∀琀攀砀琀∀ 渀愀洀攀㴀∀琀攀氀攀昀漀渀∀ 椀搀㴀∀琀攀氀攀昀漀渀∀ 瘀愀氀甀攀㴀∀㰀㼀瀀栀瀀 攀挀栀漀 栀琀洀氀猀瀀攀挀椀愀氀挀栀愀爀猀⠀␀攀洀瀀爀攀猀愀嬀✀琀攀氀攀昀漀渀✀崀 㼀㼀 ✀✀⤀㬀 㼀㸀∀㸀ഀ
            </div>਍            㰀搀椀瘀㸀ഀ
                <label for="mobil">Mòbil</label>਍                㰀椀渀瀀甀琀 琀礀瀀攀㴀∀琀攀砀琀∀ 渀愀洀攀㴀∀洀漀戀椀氀∀ 椀搀㴀∀洀漀戀椀氀∀ 瘀愀氀甀攀㴀∀㰀㼀瀀栀瀀 攀挀栀漀 栀琀洀氀猀瀀攀挀椀愀氀挀栀愀爀猀⠀␀攀洀瀀爀攀猀愀嬀✀洀漀戀椀氀✀崀 㼀㼀 ✀✀⤀㬀 㼀㸀∀㸀ഀ
            </div>਍            㰀搀椀瘀㸀ഀ
                <label for="email">Email</label>਍                㰀椀渀瀀甀琀 琀礀瀀攀㴀∀攀洀愀椀氀∀ 渀愀洀攀㴀∀攀洀愀椀氀∀ 椀搀㴀∀攀洀愀椀氀∀ 瘀愀氀甀攀㴀∀㰀㼀瀀栀瀀 攀挀栀漀 栀琀洀氀猀瀀攀挀椀愀氀挀栀愀爀猀⠀␀攀洀瀀爀攀猀愀嬀✀攀洀愀椀氀✀崀 㼀㼀 ✀✀⤀㬀 㼀㸀∀㸀ഀ
            </div>਍            㰀搀椀瘀㸀ഀ
                <label for="pagina_web">Pàgina Web</label>਍                㰀椀渀瀀甀琀 琀礀瀀攀㴀∀琀攀砀琀∀ 渀愀洀攀㴀∀瀀愀最椀渀愀开眀攀戀∀ 椀搀㴀∀瀀愀最椀渀愀开眀攀戀∀ 瘀愀氀甀攀㴀∀㰀㼀瀀栀瀀 攀挀栀漀 栀琀洀氀猀瀀攀挀椀愀氀挀栀愀爀猀⠀␀攀洀瀀爀攀猀愀嬀✀瀀愀最椀渀愀开眀攀戀✀崀 㼀㼀 ✀✀⤀㬀 㼀㸀∀㸀ഀ
            </div>਍            㰀搀椀瘀㸀ഀ
                <label for="cif_nif">CIF / NIF</label>਍                㰀椀渀瀀甀琀 琀礀瀀攀㴀∀琀攀砀琀∀ 渀愀洀攀㴀∀挀椀昀开渀椀昀∀ 椀搀㴀∀挀椀昀开渀椀昀∀ 瘀愀氀甀攀㴀∀㰀㼀瀀栀瀀 攀挀栀漀 栀琀洀氀猀瀀攀挀椀愀氀挀栀愀爀猀⠀␀攀洀瀀爀攀猀愀嬀✀挀椀昀开渀椀昀✀崀 㼀㼀 ✀✀⤀㬀 㼀㸀∀㸀ഀ
            </div>਍            㰀搀椀瘀 挀氀愀猀猀㴀∀昀甀氀氀∀㸀ഀ
                <label for="logo">Logo (JPG/PNG/WebP)</label>਍                㰀椀渀瀀甀琀 琀礀瀀攀㴀∀昀椀氀攀∀ 渀愀洀攀㴀∀氀漀最漀∀ 椀搀㴀∀氀漀最漀∀ 愀挀挀攀瀀琀㴀∀椀洀愀最攀⼀⨀∀㸀ഀ
                <?php if (!empty($empresa['logo_path']) && file_exists(__DIR__ . '/../' . $empresa['logo_path'])): ?>਍                    㰀搀椀瘀 挀氀愀猀猀㴀∀氀漀最漀ⴀ瀀爀攀瘀椀攀眀∀㸀ഀ
                        <img src="<?php echo '../' . htmlspecialchars($empresa['logo_path']); ?>" alt="Logo">਍                        㰀猀瀀愀渀㸀䄀挀琀甀愀氀㨀 㰀㼀瀀栀瀀 攀挀栀漀 栀琀洀氀猀瀀攀挀椀愀氀挀栀愀爀猀⠀戀愀猀攀渀愀洀攀⠀␀攀洀瀀爀攀猀愀嬀✀氀漀最漀开瀀愀琀栀✀崀⤀⤀㬀 㼀㸀㰀⼀猀瀀愀渀㸀ഀ
                    </div>਍                㰀㼀瀀栀瀀 攀渀搀椀昀㬀 㼀㸀ഀ
            </div>਍            㰀搀椀瘀 挀氀愀猀猀㴀∀愀挀琀椀漀渀猀 昀甀氀氀∀㸀ഀ
                <button type="submit" class="primary">Guardar</button>਍                㰀㼀瀀栀瀀 椀昀 ⠀℀攀洀瀀琀礀⠀␀攀洀瀀爀攀猀愀嬀✀氀漀最漀开瀀愀琀栀✀崀⤀⤀㨀 㼀㸀ഀ
                    <a class="secondary" href="<?php echo '../' . htmlspecialchars($empresa['logo_path']); ?>" target="_blank">Veure logo</a>਍                㰀㼀瀀栀瀀 攀渀搀椀昀㬀 㼀㸀ഀ
            </div>਍        㰀⼀昀漀爀洀㸀ഀ
    </div>਍㰀⼀戀漀搀礀㸀ഀ
</html>਍ഀ
