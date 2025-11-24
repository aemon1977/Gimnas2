import os
import time
import json
import subprocess
import urllib.parse
import urllib.request

BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
BACKUP_DIR = os.path.join(BASE_DIR, "backup")
TMP_DIR = os.path.join(BASE_DIR, "tmp")
PID_FILE = os.path.join(TMP_DIR, "telegram_poller.pid")
OFFSET_FILE = os.path.join(TMP_DIR, "telegram_offset.txt")

BOT_TOKEN = "8062658534:AAH3nGNWTiqS_n8a05yWX-wfpspTStilP5w"
CHAT_ID = 6031385210

MYSQL_BIN = r"C:\xampp\mysql\bin\mysql.exe"
MUSICAT_BIN = r"C:\xampp\mysql\bin\mysqldump.exe"  # por si se quiere ampliar con /dump
DB_USER = "root"
DB_PASS = ""
DB_NAME = "gimnas"

os.makedirs(TMP_DIR, exist_ok=True)

def read_offset():
    if os.path.isfile(OFFSET_FILE):
        try:
            return int(open(OFFSET_FILE, "r", encoding="utf-8").read().strip())
        except Exception:
            return 0
    return 0

def write_offset(offset):
    try:
        with open(OFFSET_FILE, "w", encoding="utf-8") as f:
            f.write(str(offset))
    except Exception:
        pass

def send_text(text):
    url = f"https://api.telegram.org/bot{BOT_TOKEN}/sendMessage?" + urllib.parse.urlencode({
        "chat_id": CHAT_ID,
        "text": text
    })
    try:
        urllib.request.urlopen(url, timeout=10).read()
    except Exception:
        pass

def send_file(path):
    if not os.path.isfile(path):
        send_text("Backup no trobat.")
        return
    boundary = "----WebKitFormBoundary7MA4YWxkTrZu0gW"
    data = []
    data.append(f'--{boundary}'.encode())
    data.append(f'Content-Disposition: form-data; name="chat_id"\r\n'.encode())
    data.append(f'\r\n{CHAT_ID}\r\n'.encode())
    data.append(f'--{boundary}'.encode())
    data.append(f'Content-Disposition: form-data; name="document"; filename="{os.path.basename(path)}"\r\n'.encode())
    data.append(b"Content-Type: application/octet-stream\r\n\r\n")
    data.append(open(path, "rb").read())
    data.append(f'\r\n--{boundary}--\r\n'.encode())
    body = b"".join(data)
    req = urllib.request.Request(
        url=f"https://api.telegram.org/bot{BOT_TOKEN}/sendDocument",
        data=body,
        method="POST",
        headers={"Content-Type": f"multipart/form-data; boundary={boundary}"}
    )
    try:
        urllib.request.urlopen(req, timeout=30).read()
    except Exception:
        send_text("Error en enviar backup.")

def handle_backup():
    ts = time.strftime("%Y%m%d_%H%M%S")
    dump_file = os.path.join(BACKUP_DIR, f"backup_{DB_NAME}_{ts}.sql")
    os.makedirs(BACKUP_DIR, exist_ok=True)
    cmd_parts = [f'"{MUSICAT_BIN}"', f'--user={DB_USER}']
    if DB_PASS:
        cmd_parts.append(f'--password={DB_PASS}')
    cmd_parts.append(DB_NAME)
    cmd = " ".join(cmd_parts) + f' > "{dump_file}"'
    ret = subprocess.call(cmd, shell=True)
    if ret != 0 or not os.path.isfile(dump_file) or os.path.getsize(dump_file) == 0:
        send_text("Error en crear el backup (revisa ruta/credencial de MySQL).")
        return
    send_file(dump_file)

def handle_restore(fname):
    target = os.path.abspath(os.path.join(BACKUP_DIR, fname))
    if not target.startswith(os.path.abspath(BACKUP_DIR)) or not os.path.isfile(target):
        send_text("Backup no trobat.")
        return
    cmd = f'"{MYSQL_BIN}" -u{DB_USER} -p{DB_PASS} {DB_NAME} < "{target}"'
    ret = subprocess.call(cmd, shell=True)
    send_text("Restore complet." if ret == 0 else "Error al restaurar.")

def process_update(update):
    msg = update.get("message", {})
    chat = msg.get("chat", {})
    if chat.get("id") != CHAT_ID:
        return
    text = msg.get("text", "").strip()
    if text == "/backup":
        handle_backup()
    elif text.startswith("/restore"):
        parts = text.split(" ", 1)
        if len(parts) == 2:
            handle_restore(parts[1].strip())
        else:
            send_text("Indica fitxer: /restore nom.sql")
    else:
        send_text("Ordre desconeguda. Usa /backup o /restore nom.sql")

def run():
    with open(PID_FILE, "w", encoding="utf-8") as f:
        f.write(str(os.getpid()))
    offset = read_offset()
    while True:
        url = f"https://api.telegram.org/bot{BOT_TOKEN}/getUpdates?timeout=30&offset={offset}"
        try:
            resp = urllib.request.urlopen(url, timeout=35).read()
            data = json.loads(resp.decode("utf-8"))
            for upd in data.get("result", []):
                process_update(upd)
                offset = max(offset, upd.get("update_id", 0) + 1)
                write_offset(offset)
        except Exception:
            time.sleep(5)
        time.sleep(2)

if __name__ == "__main__":
    run()
