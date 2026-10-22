docker compose exec backend composer require firebase/php-jwt
docker compose exec backend openssl genpkey -algorithm RSA -out storage/keys/jwt-private.pem -pkeyopt rsa_keygen_bits:4096

docker compose exec backend openssl rsa -pubout -in storage/keys/jwt-private.pem -out storage/keys/jwt-public.pem

docker compose exec backend php artisan migrate
docker compose exec backend php artisan tinker  
docker compose exec backend php artisan make:request Auth/RefreshTokenRequest
docker compose exec backend php artisan db:seed --class=PermissionSeeder

Aap khud kaise check karo

1. vendor/ me kul kitni PHP files hain:

docker compose exec backend sh -c "find vendor -name '*.php' | wc -l"

2. Ek request me kitni files load hoti hain. PHP ka built-in function get_included_files() batata hai ki ab tak kaunsi-kaunsi files load hui:

docker compose exec backend php -r "require 'vendor/autoload.php'; $app = require 'bootstrap/app.php'; $app->make(Illuminate\Contracts\Http\Kernel::class)->handle(Illuminate\Http\Request::create('/up')); echo count(get_included_files()), PHP_EOL;"

Isse 547 jaisa number aayega.

3. OPcache ne kitni files memory me rakhi hain (kuch requests ke baad):

docker compose exec backend php -r "var_dump(opcache_get_status(false)['opcache_statistics']['num_cached_scripts']);"

(Ye CLI process ka hai. Server process ka cache alag hota hai, lekin idea same hai.)

Step 2 (vendor ko named volume me) karne se ye 526 files Windows bridge ki jagah Linux disk se padhi jayengi. Asli speed wahi se aayegi.

