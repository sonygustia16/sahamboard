#!/usr/bin/env python3
import sys
import time
from datetime import date, timedelta
import pymysql  # Menggunakan pymysql agar lebih stabil di Python 3.14
from curl_cffi import requests

# Konfigurasi MySQL - Sesuaikan dengan database Anda
DB_CONFIG = {
    'host': 'localhost',
    'user': 'root',
    'password': '',
    'database': 'saham_db',
    'port': 3306,
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
    """Fungsi untuk menarik data dari API IDX berdasarkan tanggal"""
    try:
        r = session.get(
            'https://www.idx.co.id/primary/TradingSummary/GetStockSummary',
            headers=HEADERS, impersonate='chrome', timeout=30,
            params={'start': 0, 'length': 9999, 'date': target_date_str}
        )
        return r.json()
    except Exception as e:
        print(f"❌ Error fetching {target_date_str}: {e}")
        return None


def main():
    print("🤖 Memulai Script Penarik Data Saham (Engine: PyMySQL)...")

    # Inisialisasi session IDX
    print("⏳ Menghubungkan ke server IDX...")
    session = requests.Session()
    try:
        session.get('https://www.idx.co.id/id', headers=HEADERS, impersonate='chrome', timeout=15)
    except Exception as e:
        print(f"❌ Gagal mengetuk server IDX: {e}")

    # ============================================================
    # MODE OTOMATIS:
    # - "full"  -> tarik dari 2024-01-01 (backfill penuh, dipakai SEKALI di awal saja)
    # - default -> cuma tarik 5 hari terakhir (mode harian, dipakai untuk update rutin)
    #
    # Contoh penggunaan:
    #   python tarik_data.py full     -> backfill penuh (jalankan manual sekali)
    #   python tarik_data.py          -> mode harian (cocok dijadwalkan tiap hari)
    # ============================================================
    if len(sys.argv) > 1 and sys.argv[1] == 'full':
        start_date = date(2024, 1, 1)
        print("📦 Mode: FULL BACKFILL (dari 2024-01-01, otomatis skip yang sudah ada)")
    elif len(sys.argv) > 1 and sys.argv[1] == 'gap':
        # Mode "gap": mulai dari tanggal terakhir yang ADA di database,
        # cocok dipakai kalau kelewat lama nggak narik data (lebih cepat dari "full"
        # karena tidak perlu loop+cek tanggal yang sudah pasti lama ada).
        temp_conn = pymysql.connect(**DB_CONFIG)
        temp_cursor = temp_conn.cursor()
        temp_cursor.execute('SELECT MAX(date) FROM ringkasan_saham')
        last_date = temp_cursor.fetchone()[0]
        temp_cursor.close()
        temp_conn.close()

        if last_date:
            start_date = last_date + timedelta(days=1)
            print(f"📦 Mode: GAP-FILL (lanjut dari {start_date}, data terakhir di DB: {last_date})")
        else:
            start_date = date(2024, 1, 1)
            print("📦 Mode: GAP-FILL (tabel masih kosong, mulai dari 2024-01-01)")
    else:
        start_date = date.today() - timedelta(days=5)
        print("📦 Mode: HARIAN (5 hari terakhir saja, untuk update rutin)")

    end_date = date.today()
    current_date = start_date

    # Koneksi ke MySQL dengan PyMySQL
    print("⏳ Menghubungkan ke database MySQL...")
    try:
        conn = pymysql.connect(**DB_CONFIG)
        cursor = conn.cursor()
        print("✅ MySQL Terhubung!")
    except Exception as e:
        print(f"❌ GAGAL TERHUBUNG KE MYSQL! Masalahnya adalah: {e}")
        input("\nTekan ENTER untuk keluar...")
        return

    while current_date <= end_date:
        # Skip hari Sabtu (5) dan Minggu (6) untuk menghemat kuota API
        if current_date.weekday() in [5, 6]:
            current_date += timedelta(days=1)
            continue

        date_str = current_date.strftime('%Y-%m-%d')

        # Cek apakah tanggal ini sudah ada di MySQL (skip kalau sudah, kecuali mode harian
        # yang sengaja menarik ulang 5 hari terakhir untuk jaga-jaga ada data yang ke-update/revisi)
        cursor.execute('SELECT COUNT(*) FROM ringkasan_saham WHERE date = %s', (date_str,))
        already_exists = cursor.fetchone()[0] > 0

        is_full_mode = len(sys.argv) > 1 and sys.argv[1] == 'full'
        if already_exists and is_full_mode:
            print(f'✅ {date_str}: Sudah ada di MySQL (Skipped)')
            current_date += timedelta(days=1)
            continue

        print(f'⏳ Menarik data untuk tanggal: {date_str}...')
        data = fetch_data_by_date(session, date_str)

        if not data or data.get('recordsTotal', 0) == 0:
            print(f'➖ {date_str}: Tidak ada data (Hari libur / pasar tutup)')
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
                row.get('IndexIndividual'),
                row.get('Offer'), row.get('OfferVolume'),
                row.get('Bid'), row.get('BidVolume'),
                row.get('ListedShares'), row.get('TradebleShares'),
                row.get('WeightForIndex'),
                row.get('ForeignSell'), row.get('ForeignBuy'),
                row.get('NonRegularVolume'), row.get('NonRegularValue'),
                row.get('NonRegularFrequency'), row.get('Remarks'),
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
                stock_name=VALUES(stock_name),
                previous=VALUES(previous),
                close=VALUES(close),
                `change`=VALUES(`change`),
                volume=VALUES(volume),
                `value`=VALUES(`value`),
                frequency=VALUES(frequency);
        """

        try:
            cursor.executemany(query, rows)
            print(f'🚀 {date_str}: Sukses memasukkan/update {len(rows)} data saham.')
        except Exception as e:
            print(f'❌ Gagal menyimpan data tanggal {date_str}: {e}')

        current_date += timedelta(days=1)
        time.sleep(2)

    cursor.close()
    conn.close()
    print("✨ Selesai!")


if __name__ == '__main__':
    main()
