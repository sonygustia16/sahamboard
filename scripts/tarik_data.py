#!/usr/bin/env python3
import sys
import time
from datetime import date, timedelta
import pymysql  # Tetap menggunakan pymysql
from curl_cffi import requests
from apscheduler.schedulers.blocking import BlockingScheduler
from apscheduler.triggers.cron import CronTrigger

# ============================================================
# PENTING: KARENA JALAN DI RAILWAY, GUNAKAN PORT INTERNAL (3306)
# DAN HOST INTERNAL 'mysql.railway.internal' KARENA SATU JARINGAN
# ============================================================
DB_CONFIG = {
    'host': 'mysql.railway.internal', # Host internal untuk sesama server Railway
    'user': 'root',                   # Sesuaikan jika user Anda berbeda
    'password': 'nwjciFdGAdBfKPTwIyBYaIGDZxgnKgiD', # Ganti dengan password MySQL Railway Anda
    'database': 'railway',            # Nama database Anda di Railway
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

def tugas_tarik_data():
    print(f"\n⚡ [{date.today()}] Memulai penarikan data harian otomatis...")
    
    session = requests.Session()
    try:
        session.get('https://www.idx.co.id/id', headers=HEADERS, impersonate='chrome', timeout=15)
    except Exception as e:
        print(f"❌ Gagal mengetuk server IDX: {e}")

    # Mode Harian: Menarik data 5 hari terakhir untuk jaga-jaga ada revisi/gap data
    start_date = date.today() - timedelta(days=5)
    end_date = date.today()
    current_date = start_date

    try:
        conn = pymysql.connect(**DB_CONFIG)
        cursor = conn.cursor()
        print("✅ Berhasil terhubung ke database Railway!")
    except Exception as e:
        print(f"❌ GAGAL TERHUBUNG KE MYSQL: {e}")
        return

    while current_date <= end_date:
        # Skip hari Sabtu & Minggu
        if current_date.weekday() in [5, 6]:
            current_date += timedelta(days=1)
            continue

        date_str = current_date.strftime('%Y-%m-%d')
        print(f'⏳ Memeriksa/Menarik data tanggal: {date_str}...')
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
            print(f'🚀 {date_str}: Sukses memperbarui {len(rows)} data saham.')
        except Exception as e:
            print(f'❌ Gagal menyimpan data tanggal {date_str}: {e}')

        current_date += timedelta(days=1)
        time.sleep(2)

    cursor.close()
    conn.close()
    print("✨ Selesai sinkronisasi data hari ini! Kembali standby...")

if __name__ == '__main__':
    print("🤖 Engine Otomatisasi Penarik Data Aktif!")
    
    # Jalankan sekali di awal saat server dinyalakan agar data langsung up-to-date
    tugas_tarik_data()
    
    # Daftarkan jadwal rutin harian
    scheduler = BlockingScheduler()
    
    # Dijadwalkan Senin-Jumat pukul 17:30 WIB (Server Railway biasanya memakai zona waktu UTC, 17:30 WIB = 10:30 UTC)
    # Kita kunci menggunakan timezone Asia/Jakarta agar akurat dengan jam lokal kita
    trigger = CronTrigger(day_of_week='mon-fri', hour=17, minute=30, timezone='Asia/Jakarta')
    
    scheduler.add_job(tugas_tarik_data, trigger=trigger)
    print("⏰ Jadwal Terpasang: Otomatis berjalan setiap Senin-Jumat jam 17:30 WIB.")
    
    try:
        scheduler.start()
    except (KeyboardInterrupt, SystemExit):
        print("🤖 Engine Otomatisasi Dimatikan.")