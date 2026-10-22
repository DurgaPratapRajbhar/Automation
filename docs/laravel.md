docker compose exec backend composer require firebase/php-jwt
docker compose exec backend openssl genpkey -algorithm RSA -out storage/keys/jwt-private.pem -pkeyopt rsa_keygen_bits:4096

docker compose exec backend openssl rsa -pubout -in storage/keys/jwt-private.pem -out storage/keys/jwt-public.pem

docker compose exec backend php artisan migrate
docker compose exec backend php artisan tinker  
docker compose exec backend php artisan make:request Auth/RefreshTokenRequest
docker compose exec backend php artisan db:seed --class=PermissionSeeder