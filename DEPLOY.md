# Deploy & Queue Worker — Juki Tools

## Urutan deploy (WAJIB berurutan)

```bash
cd /path/ke/tools
git pull origin master
php artisan migrate --force        # kolom baru WAJIB dibuat sebelum kode dipakai
php artisan config:clear
php artisan queue:restart          # worker memuat ulang kode (butuh supervisor agar hidup lagi)
```

> Jika `migrate` dilewati tapi kode sudah ter-deploy, endpoint cluster SILO akan
> error 500 (`Unknown column 'api_key_website_id'`) dan job yang menyentuh tabel
> tersebut gagal.

## Menjalankan worker permanen (supervisor)

Tanpa supervisor, worker mati saat reboot/timeout dan tidak hidup sendiri —
gejalanya: tombol generate "tidak melakukan apa-apa" karena job menumpuk di
tabel `jobs`.

1. Salin `deploy/supervisor-tools-worker.conf` ke
   `/etc/supervisor/conf.d/tools-worker.conf`, sesuaikan path & user.
2. `sudo supervisorctl reread && sudo supervisorctl update`
3. Verifikasi: `sudo supervisorctl status tools-worker:*` → RUNNING.

## Cepat memeriksa kesehatan queue

```bash
php artisan tinker --execute='echo \Illuminate\Support\Facades\DB::table("jobs")->count();'   # antrian menumpuk = worker mati
php artisan queue:failed                                                                      # job gagal + alasannya
php artisan queue:retry all                                                                   # ulangi semua job gagal
tail -f storage/logs/worker.log                                                               # aktivitas worker
php artisan queue:work database --stop-when-empty                                             # tes proses sekali jalan
```

## Cek cepat "tombol tidak merespon"

1. `QUEUE_CONNECTION` di `.env` produksi:
   - `sync` → tidak butuh worker; masalah bukan di queue.
   - `database` → worker WAJIB berjalan (supervisor).
2. Job menumpuk di tabel `jobs` → worker mati → jalankan supervisor di atas.
3. Baru saja deploy tanpa `queue:restart`? Worker masih memakai kode lama di memori.
