# Image untuk harness lokal panel admin DocLink.
#
# Sengaja TIDAK menyalin kode ke dalam image: kode dipasang lewat volume, jadi
# image hanya perlu PHP + ekstensi + konfigurasi Apache. `Dockerfile` di akar
# repo tetap dipakai untuk deployment (Railway) dan menyalin seluruh repo;
# menyalin 373 MB hanya untuk pratinjau lokal jelas tidak sepadan.
#
# Ekstensi mengikuti pemakaian nyata di `admin_menu/`: mysqli (database),
# mbstring (CodeIgniter + teks Indonesia), gd/exif (gambar unggahan),
# intl (format tanggal/angka), zip (ekspor laporan).
#
# Python dan git sengaja TIDAK dipasang, dan itu bukan kelalaian: tiga dari
# lima pemeriksa di `tools/admin_panel/local/checks/` membaca `git show HEAD:`
# — membandingkan hasil kerja dengan commit terakhir, yang hanya bermakna di
# mesin pengembang. Di dalam kontainer repo dipasang sebagai volume milik
# pengguna mesin sementara prosesnya root, sehingga git menolaknya dengan
# "dubious ownership". Kelima pemeriksa itu dijalankan dari mesin; `gates.sh`
# menghitungnya "dilewati" bila dijalankan di kontainer. Node juga tidak
# dipasang: uji Playwright dijalankan dari mesin.
FROM php:8.1-apache

RUN apt-get update \
	&& apt-get install -y --no-install-recommends \
		libfreetype6-dev \
		libjpeg62-turbo-dev \
		libpng-dev \
		libzip-dev \
		libicu-dev \
		libonig-dev \
	&& docker-php-ext-configure gd --with-freetype --with-jpeg \
	&& docker-php-ext-install -j"$(nproc)" mysqli pdo pdo_mysql mbstring gd exif intl zip \
	&& a2enmod rewrite headers \
	&& rm -rf /var/lib/apt/lists/*

# DocumentRoot diarahkan ke `admin_menu/`, bukan akar repo. Alasannya konkret:
# `.htaccess` di akar repo memuat pengalihan paksa ke HTTPS yang akan
# menjebak semua permintaan lokal dalam pengalihan berulang. Dengan
# DocumentRoot di `admin_menu/`, hanya `.htaccess` milik aplikasi yang berlaku.
COPY vhost.conf /etc/apache2/sites-available/000-default.conf

# Direktori sesi dipastikan ada dan dapat ditulis www-data. `DOCLINC_SESSION_PATH`
# menunjuk ke sini (lihat docker-compose.yml), dan sesi yang tidak bisa ditulis
# membuat login tampak berhasil tetapi langsung terlempar kembali ke formulir.
RUN mkdir -p /var/lib/php/sessions \
	&& chown www-data:www-data /var/lib/php/sessions \
	&& chmod 1733 /var/lib/php/sessions

WORKDIR /var/www/html

