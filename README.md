# install backend laravel PHP
composer install

npm install 
# create file .env and .env.example
copy .env.example .env (windows)

cp .env.example .env (git bash/ terminal Linux/Mac)
# create key artisan
php artisan key:generate
# migrate database for database
php artisan migrate
# run server
php artisan serve
# run npm
npm run dev