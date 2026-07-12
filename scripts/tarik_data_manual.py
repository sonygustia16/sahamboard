#!/usr/bin/env python3
"""
SahamBoard - Tarik Data Manual (versi jalan sekali, tanpa scheduler)
=====================================================================
Beda dari versi lama: script ini TIDAK masuk mode "standby menunggu
jadwal 17:30 WIB". Begitu proses tarik data selesai, script langsung
berhenti sendiri. Cocok dipakai manual kapan pun kamu butuh update data,
lewat double-click .bat atau `python tarik_data_manual.py` dari terminal.

Kredensial di bawah pakai HOST + PORT PUBLIK Railway (MYSQL_PUBLIC_URL),
karena script ini dijalankan dari laptop/PC kamu sendiri, bukan dari
dalam jaringan internal Railway.
"""
import time
from datetime import date, timedelta
import pymysql
from curl_cffi import requests

DB_CONFIG = {
    'host': 'thomas.proxy.rlwy.net',   # Host publik Railway
    'user': 'root',
    'password': 'nwjciFdGAdBfKPTwIyBYaIGDZxgnKgiD',
    'database': 'railway',
    'port': 15597,                      # Port publik Railway
    'autocommit': True
}

HEADERS = {
    'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
    'Accept': 'application/json, text/plain, */*',
    'Accept-Language': 'id-ID,id;q=0.9,en-US;q=0.8,en;q=0.7',
    'Referer': 'https://www.idx.co.id/id/data-pasar/ringkasan-perdagangan/ringkasan-saham',
    'Origin': 'https://www.idx.co.id',
}


def fetch_data_by_date(session, target_date_str):
    try:
        r = session.get(
            'https://www.idx.co.id/primary/TradingSummary/GetStockSummary',
            headers=HEADERS, impersonate='chrome', timeout=30,
            params={'start': 0, 'length': 9999, 'date': target_date_str}
        )
        return r.json()
    except Exception as e:
        print(f"[X] Error fetching {target_date_str}: {e}")
        return None


def get_last_date_in_db(cursor):
    cursor.execute("SELECT MAX(date) FROM ringkasan_saham")
    row = cursor.fetchone()
    return row[0] if row and row[0] else None


def tarik_rentang(session, cursor, start_date, end_date, skip_existing=False, existing_dates=None):
    current_date = start_date
    total_hari_sukses = 0

    while current_date <= end_date:
        if current_date.weekday() in [5, 6]:  # skip Sabtu/Minggu
            current_date += timedelta(days=1)
            continue

        date_str = current_date.strftime('%Y-%m-%d')

        if skip_existing and existing_dates and current_date in existing_dates:
            print(f'[-] {date_str}: sudah ada di database, dilewati.')
            current_date += timedelta(days=1)
            continue

        print(f'[..] Memeriksa/Menarik data tanggal: {date_str}...')
        data = fetch_data_by_date(session, date_str)

        if not data or data.get('recordsTotal', 0) == 0:
            print(f'[-] {date_str}: Tidak ada data (Hari libur / pasar tutup)')
            current_date += timedelta(days=1)
            time.sleep(1)
            continue

        rows = []
        for row in data['data']:
            rows.append((
                date_str, row['StockCode'], row['StockName'],
                row.get('Previous'), row.get('OpenPrice'), row.get('FirstTrade'),
                row.get('High'), row.get('Low'), row.get('Close'), row.get('Change'),
                row.get('Volume'), row.get('Value'), row.get('Frequency'),
                row.get('IndexIndividual'), row.get('Offer'), row.get('OfferVolume'),
                row.get('Bid'), row.get('BidVolume'), row.get('ListedShares'),
                row.get('TradebleShares'), row.get('WeightForIndex'),
                row.get('ForeignSell'), row.get('ForeignBuy'), row.get('NonRegularVolume'),
                row.get('NonRegularValue'), row.get('NonRegularFrequency'), row.get('Remarks'),
            ))

        query = """
            INSERT INTO ringkasan_saham (
                date, stock_code, stock_name, previous, open_price, first_trade,
                high, low, close, `change`, volume, `value`, frequency, index_individual,
                offer, offer_volume, bid, bid_volume, listed_shares, tradeble_shares,
                weight_for_index, foreign_sell, foreign_buy, non_regular_volume,
                non_regular_value, non_regular_frequency, remarks
            ) VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
            ON DUPLICATE KEY UPDATE
                stock_name=VALUES(stock_name), previous=VALUES(previous), close=VALUES(close),
                `change`=VALUES(`change`), volume=VALUES(volume), `value`=VALUES(`value`), frequency=VALUES(frequency);
        """

        try:
            cursor.executemany(query, rows)
            print(f'[OK] {date_str}: Sukses memperbarui {len(rows)} data saham.')
            total_hari_sukses += 1
        except Exception as e:
            print(f'[X] Gagal menyimpan data tanggal {date_str}: {e}')

        current_date += timedelta(days=1)
        time.sleep(2)

    return total_hari_sukses


def main():
    print("=" * 60)
    print("  SAHAMBOARD - TARIK DATA MANUAL (jalan sekali, auto-exit)")
    print("=" * 60)
    print()
    print("Pilih mode penarikan data:")
    print()
    print(" [1] GAP MODE  - Lanjut dari tanggal terakhir di database")
    print("                 (PALING DISARANKAN jika sudah lama tidak narik)")
    print()
    print(" [2] HARIAN    - 5 hari terakhir saja")
    print("                 (untuk update rutin harian)")
    print()
    print(" [3] FULL      - Dari 2024 sampai hari ini")
    print("                 (skip yang sudah ada, proses paling lama)")
    print()

    pilihan = input("Masukkan pilihan (1/2/3): ").strip()

    try:
        conn = pymysql.connect(**DB_CONFIG)
        cursor = conn.cursor()
        print("[OK] Berhasil terhubung ke database Railway!")
    except Exception as e:
        print(f"[X] GAGAL TERHUBUNG KE MYSQL: {e}")
        input("\nTekan Enter untuk keluar...")
        return

    session = requests.Session()
    try:
        session.get('https://www.idx.co.id/id', headers=HEADERS, impersonate='chrome', timeout=15)
    except Exception as e:
        print(f"[X] Gagal mengetuk server IDX: {e}")

    today = date.today()

    if pilihan == '1':
        print("\n[GAP MODE] Memulai penarikan dari tanggal terakhir di database...")
        last_date = get_last_date_in_db(cursor)
        if last_date:
            start_date = last_date + timedelta(days=1)
            print(f"Tanggal terakhir di DB: {last_date}. Menarik mulai dari {start_date}...")
        else:
            start_date = date(2024, 1, 1)
            print("Database masih kosong. Menarik mulai dari 2024-01-01...")
        total = tarik_rentang(session, cursor, start_date, today)

    elif pilihan == '2':
        print("\n[HARIAN] Menarik data 5 hari terakhir...")
        start_date = today - timedelta(days=5)
        total = tarik_rentang(session, cursor, start_date, today)

    elif pilihan == '3':
        print("\n[FULL] Menarik data dari 2024-01-01 sampai hari ini (skip yang sudah ada)...")
        cursor.execute("SELECT DISTINCT date FROM ringkasan_saham")
        existing_dates = {r[0] for r in cursor.fetchall()}
        start_date = date(2024, 1, 1)
        total = tarik_rentang(session, cursor, start_date, today, skip_existing=True, existing_dates=existing_dates)

    else:
        print("[X] Pilihan tidak valid. Keluar.")
        cursor.close()
        conn.close()
        return

    cursor.close()
    conn.close()

    print()
    print(f"[SELESAI] Total {total} hari berhasil diproses. Script otomatis keluar.")
    input("\nTekan Enter untuk menutup jendela ini...")


if __name__ == '__main__':
    main()