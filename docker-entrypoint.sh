#!/bin/sh
# ═══════════════════════════════════════════════════════════════════════════
#  Entrypoint produksi — satu image, empat peran (CONTAINER_ROLE)
# ═══════════════════════════════════════════════════════════════════════════
#
# `app`, `horizon`, `reverb`, `scheduler` di docker-compose.prod.yml semua
# pakai image YANG SAMA (hasil Dockerfile.prod) — cuma CONTAINER_ROLE beda.
# Migrate/cache/storage:link WAJIB cuma dijalankan sekali oleh role `app`,
# BUKAN oleh keempatnya paralel: kalau semua jalan `migrate --force`
# bersamaan saat container start bareng, race condition di penguncian tabel
# `migrations` bisa bikin migrasi gagal setengah jalan atau dobel-jalan.
# Role lain (horizon/reverb/scheduler) menunggu `app` sehat lebih dulu lewat
# `depends_on: condition: service_healthy` di compose — jadi saat mereka
# start, migrate dari `app` sudah pasti selesai.
set -e

ROLE="${CONTAINER_ROLE:-app}"

wait_for_tcp() {
    host="$1"
    port="$2"
    label="$3"
    [ -z "$host" ] && return 0
    echo "⏳ Menunggu $label ($host:$port)..."
    tries=0
    until nc -z "$host" "$port" 2>/dev/null; do
        tries=$((tries + 1))
        if [ "$tries" -ge 60 ]; then
            echo "❌ $label tidak siap setelah 60 detik, keluar."
            exit 1
        fi
        sleep 1
    done
    echo "✅ $label siap."
}

if [ "$DB_CONNECTION" = "mysql" ]; then
    wait_for_tcp "$DB_HOST" "${DB_PORT:-3306}" "database"
fi
wait_for_tcp "$REDIS_HOST" "${REDIS_PORT:-6379}" "redis"

if [ "$ROLE" = "app" ]; then
    # Buang marker siap dari boot sebelumnya. /tmp ikut bertahan di
    # `docker restart` (filesystem container sama), jadi tanpa ini healthcheck
    # langsung lolos sebelum migrate+cache di bawah selesai dan horizon/nginx
    # yang gate di `app: service_healthy` start di skema lama.
    rm -f /tmp/app-ready

    # Volume `cache_data` menutupi bootstrap/cache image dan bertahan antar
    # deploy, jadi packages.php/services.php/config.php di dalamnya bisa
    # BASI: dari deploy sebelumnya, atau (kalau image dibangun lokal) terbawa
    # `COPY .` dari mesin dev dan menyebut provider paket dev (Boost dst)
    # yang tidak ada di vendor prod → package:discover mati dengan
    # `Class "Laravel\Boost\BoostServiceProvider" not found`. Buang semua
    # sebelum discover; langkah di bawah menulis ulang yang benar.
    rm -f bootstrap/cache/*.php

    # Volume `storage_data` juga bertahan: subfolder yang baru ditambah image
    # tidak ikut muncul di volume lama. view:cache & session butuh ini ada.
    mkdir -p storage/logs storage/app/public \
        storage/framework/cache/data storage/framework/sessions storage/framework/views

    # package:discover diulang di sini (bukan cuma saat build) karena tahap
    # `vendor` di Dockerfile.prod jalan --no-scripts sebelum kode app ada —
    # provider paket pihak ketiga (Horizon, Reverb, dll) belum terdaftar
    # sampai baris ini.
    echo "🔧 package:discover..."
    php artisan package:discover --ansi

    echo "🗄️  Menjalankan migrasi..."
    php artisan migrate --force

    # storage:link error kalau symlink sudah ada (volume storage persisten
    # antar deploy) — cek dulu supaya redeploy kedua dst tidak gagal di sini.
    if [ ! -L /var/www/public/storage ]; then
        echo "🔗 Membuat storage:link..."
        php artisan storage:link
    fi

    echo "⚡ Cache config/route/view/event..."
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan event:cache
fi

case "$ROLE" in
    app)
        # Marker dibaca HEALTHCHECK service `app` di docker-compose.prod.yml.
        # `php artisan --version` doang lolos begitu vendor/autoload ada —
        # jauh sebelum migrate/cache di atas kelar — jadi horizon/reverb/
        # scheduler/nginx (semua gate di app: service_healthy) bisa start
        # dini kalau cuma itu yang dicek. Marker ini ditulis TEPAT SEBELUM
        # exec php-fpm, sesudah migrate+cache selesai.
        touch /tmp/app-ready
        echo "🚀 Starting PHP-FPM..."
        exec "$@"
        ;;
    horizon)
        echo "🚀 Starting Horizon..."
        exec php artisan horizon
        ;;
    reverb)
        echo "🚀 Starting Reverb..."
        exec php artisan reverb:start --host="0.0.0.0" --port="${REVERB_SERVER_PORT:-8081}"
        ;;
    scheduler)
        echo "🚀 Starting Scheduler..."
        exec php artisan schedule:work
        ;;
    *)
        echo "❌ CONTAINER_ROLE tidak dikenal: $ROLE"
        exit 1
        ;;
esac
