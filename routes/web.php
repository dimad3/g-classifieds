<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\ActionController;
use App\Http\Controllers\Admin\Adverts\AttributeController;
use App\Http\Controllers\Admin\Adverts\CategoryController;
use App\Http\Controllers\Admin\Adverts\ManageController as AdminManageController;
// use App\Http\Controllers\Admin\Adverts\OptionController;
use App\Http\Controllers\Admin\BannerController as AdminBannerController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\PageController as AdminPageController;
use App\Http\Controllers\Admin\RegionController;
use App\Http\Controllers\Admin\TicketController as AdminTicketController;
use App\Http\Controllers\Admin\UploadController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AdvertController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\NetworkController;
use App\Http\Controllers\BannerController;
use App\Http\Controllers\Cabinet\Adverts\AdvertController as CabinetAdvertController;
use App\Http\Controllers\Cabinet\Adverts\CreateController as AdvertsCreateController;
use App\Http\Controllers\Cabinet\Adverts\Dialogs\DialogController;
use App\Http\Controllers\Cabinet\Adverts\Dialogs\MessageController;
use App\Http\Controllers\Cabinet\Banners\BannerController as CabinetBannerController;
use App\Http\Controllers\Cabinet\Banners\CreateController as BannersCreateController;
use App\Http\Controllers\Cabinet\FavoriteController;
use App\Http\Controllers\Cabinet\PhoneController;
use App\Http\Controllers\Cabinet\ProfileController;
use App\Http\Controllers\Cabinet\TicketController as CabinetTicketController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\TestController;
use App\Http\Middleware\FilledProfile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

// Public (Guest) routes ====================================================

Route::get('/', [HomeController::class, 'index'])->name('home');
// ['verify' => true] - enable email verification routes for authentication:
// ['verification.notice', 'verification.verify', 'verification.resend']
// This option automatically registers routes required for email verification,
// including sending the verification email and verifying the user's email address
// through a signed URL when the user attempts to log in.
Auth::routes(['verify' => true]);

Route::get('/login/phone', [LoginController::class, 'showLoginWithSmsForm'])->name('login.phone');
Route::post('/login/phone', [LoginController::class, 'loginWithSmsToken']);

Route::get('/login/{network}', [NetworkController::class, 'redirect'])->name('login.network');
Route::get('/login/{network}/callback', [NetworkController::class, 'callback']);

Route::get('/banner/get', [BannerController::class, 'get'])->name('banner.get');
Route::get('/banner/{banner}/click', [BannerController::class, 'click'])->name('banner.click');

Route::group(['prefix' => 'adverts', 'as' => 'adverts.'], function (): void {
    Route::get('/show/{advert}', [AdvertController::class, 'show'])->name('show');
    Route::post('/show/{advert}/phone', [AdvertController::class, 'phone'])->name('phone');
    Route::get('/search', [AdvertController::class, 'search'])->name('search');

    // Purpose: This route captures all paths that match the adverts_path pattern and directs them to the index method of the AdvertController.
    // see chatgpt - explanations\2024.09.21 urls constraints.docx
    // where() - route Constraints https://laravel.com/docs/8.x/routing#parameters-regular-expression-constraints
    Route::get('/{adverts_path?}', [AdvertController::class, 'index'])->name('index')->where('adverts_path', '.+');
});

// Cabinet routes ====================================================

Route::group(
    [
        'prefix' => 'cabinet',
        'as' => 'cabinet.',
        'middleware' => ['auth', 'verified'],
    ],
    function (): void {
        // Profile routes
        Route::group(['prefix' => 'profile', 'as' => 'profile.'], function (): void {
            Route::get('/', [ProfileController::class, 'show'])->name('show');
            Route::get('/edit', [ProfileController::class, 'edit'])->name('edit');
            Route::put('/update', [ProfileController::class, 'update'])->name('update');

            Route::post('/phone', [PhoneController::class, 'requestPhoneVerificationToken'])->name('phone.request_verification_token');
            Route::get('/phone', [PhoneController::class, 'showVerificationTokenForm'])->name('phone.show_verification_token_form');
            Route::put('/phone', [PhoneController::class, 'verifyPhone'])->name('phone.verify_token');

            Route::post('/phone/auth', [PhoneController::class, 'togglePhoneAuth'])->name('toggle_phone_auth');
        });

        // Adverts routes
        Route::group([
            'prefix' => 'adverts',
            'as' => 'adverts.',
            'middleware' => [FilledProfile::class],
        ], function (): void {
            // 'FilledProfile' middleware is not applied to these routes
            Route::get('/', [CabinetAdvertController::class, 'index'])
                ->name('index')
                ->withoutMiddleware(FilledProfile::class);
            Route::get('/my-dialogs', [CabinetAdvertController::class, 'advertsWithUserDialogs'])
                ->name('my_dialogs.index')
                ->withoutMiddleware(FilledProfile::class);

            Route::get('/{advert}/edit', [CabinetAdvertController::class, 'edit'])->name('edit');
            Route::put('/{advert}/edit', [CabinetAdvertController::class, 'update']); // do not change url -> we do not need specify route in form's action attribute
            Route::delete('/{advert}/destroy', [CabinetAdvertController::class, 'destroy'])->name('destroy');
            Route::post('/{advert}/send-to-moderation', [CabinetAdvertController::class, 'sendToModeration'])->name('send_to_moderation');
            Route::post('/{advert}/close', [CabinetAdvertController::class, 'close'])->name('close');
            Route::post('/{advert}/restore', [CabinetAdvertController::class, 'restore'])->name('restore');
            Route::post('/{advert}/revert-to-draft', [CabinetAdvertController::class, 'revertToDraft'])->name('revert');

            // create advert
            Route::get('/create', [AdvertsCreateController::class, 'selectRootCategory'])->name('create.select_root_category');
            Route::get('/create/{category}/select-subcategory', [AdvertsCreateController::class, 'selectSubCategory'])->name('create.select_sub_category');
            Route::get('/create/{category}/{region?}', [AdvertsCreateController::class, 'selectRegion'])->name('create.select_region');
            Route::get('/create/{category}/{region}/select-action', [AdvertsCreateController::class, 'selectAction'])->name('create.select_action');
            Route::get('/create/advert/{category}/{region}', [AdvertsCreateController::class, 'create'])->name('create');
            Route::post('/create/advert/{category}/{region}', [AdvertsCreateController::class, 'store'])->name('store');
        });

        // Favorites routes
        Route::get('favorites', [FavoriteController::class, 'index'])->name('favorites.index');
        Route::post('favorites/{advert}', [FavoriteController::class, 'addToFavorites'])->name('favorites.add');
        Route::delete('favorites/{advert}', [FavoriteController::class, 'remove'])->name('favorites.remove');

        // Banners routes
        Route::group([
            'prefix' => 'banners',
            'as' => 'banners.',
            'middleware' => [FilledProfile::class],
        ], function (): void {
            Route::get('/', [CabinetBannerController::class, 'index'])->name('index');
            Route::get('/create', [BannersCreateController::class, 'category'])->name('create');
            Route::get('/create/region/{category}/{region?}', [BannersCreateController::class, 'region'])->name('create.region');
            Route::get('/create/banner/{category}/{region?}', [BannersCreateController::class, 'banner'])->name('create.banner');
            Route::post('/create/banner/{category}/{region?}', [BannersCreateController::class, 'store'])->name('create.banner.store');

            Route::get('/show/{banner}', [CabinetBannerController::class, 'show'])->name('show');
            Route::get('/{banner}/edit', [CabinetBannerController::class, 'editForm'])->name('edit');
            Route::put('/{banner}/edit', [CabinetBannerController::class, 'edit']);
            Route::get('/{banner}/file', [CabinetBannerController::class, 'fileForm'])->name('file');
            Route::put('/{banner}/file', [CabinetBannerController::class, 'file']);
            Route::post('/{banner}/send', [CabinetBannerController::class, 'send'])->name('send');
            Route::post('/{banner}/cancel', [CabinetBannerController::class, 'cancel'])->name('cancel');
            Route::post('/{banner}/order', [CabinetBannerController::class, 'order'])->name('order');
            Route::delete('/{banner}/destroy', [CabinetBannerController::class, 'destroy'])->name('destroy');
        });

        // Tickets routes
        Route::resource('tickets', CabinetTicketController::class);
        Route::post('tickets/{ticket}/add-message', [CabinetTicketController::class, 'addMessage'])->name('tickets.add_message');

        // Dialogs routes
        // see https://laravel.com/docs/10.x/controllers#shallow-nesting
        Route::resource('adverts.dialogs', DialogController::class)->shallow()->except(['edit', 'update']);

        // Messages routes
        // see https://laravel.com/docs/10.x/controllers#shallow-nesting
        Route::resource('dialogs.messages', MessageController::class)->shallow()->only(['store']);
    }
);

// Admin routes ====================================================

Route::group([
    'prefix' => 'admin',
    'as' => 'admin.',
    'middleware' => ['auth', 'can:admin-panel'],
], function (): void {
    Route::post('/ajax/upload/image', [UploadController::class, 'image'])->name('ajax.upload.image');

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::resource('actions', ActionController::class)->except('show');
    Route::resource('regions', RegionController::class);
    Route::resource('pages', AdminPageController::class);

    // Adverts routes
    Route::group(['prefix' => 'adverts', 'as' => 'adverts.'], function (): void {
        Route::resource('categories', CategoryController::class);

        // todo: use shallow() for attributes' routes
        Route::group(['prefix' => 'categories/{category}', 'as' => 'categories.'], function (): void {
            Route::resource('attributes', AttributeController::class)->except('index');
            Route::post('attributes/import', [CategoryController::class, 'attributesImport'])->name('attributes.import');
            Route::post('attributes/settings', [CategoryController::class, 'storeSettings'])->name('attributes.settings.store');
            Route::post('attributes/excluded-attributes', [CategoryController::class, 'storeExcludedAttributes'])->name('attributes.excluded.store');

            Route::get('actions', [CategoryController::class, 'selectActions'])->name('actions.create');
            Route::post('actions', [CategoryController::class, 'storeActions'])->name('actions.store');
            Route::post('attributes/excluded-actions', [CategoryController::class, 'storeExcludedActions'])->name('actions.excluded.store');

            Route::post('attributes/{attribute}/settings', [AttributeController::class, 'storeSettings'])->name('actions.settings.store');
            Route::get('all-attributes/settings', [AttributeController::class, 'selectSettingsForAllAttributes'])->name('all_attributes.settings.create');
            Route::post('all-attributes/settings', [AttributeController::class, 'storeSettingsForAllAttributes'])->name('all_attributes.settings.store');

            // Route::group(['prefix' => 'attributes/{attribute}', 'as' => 'attributes.'], function () {
            //     Route::resource('options', OptionController::class)->except('index');
            // });
        });
        // Route::post('attributes/{attribute}', [AttributeController::class, 'storeSettings'])->name('attributes.settings.store');

        Route::group(['prefix' => 'categories/{parentCategory}', 'as' => 'categories.'], function (): void {
            Route::get('/subcategories', [CategoryController::class, 'getSubCategories'])->name('subcats.index');
            Route::get('/createsubcategory', [CategoryController::class, 'createSubCategory'])->name('subcats.create');
            Route::post('/storesubcategory', [CategoryController::class, 'storeSubCategory'])->name('subcats.store');
            Route::post('subcategories/import', [CategoryController::class, 'subCategoriesesImport'])->name('subcats.import');
        });

        Route::group(['prefix' => 'manage', 'as' => 'manage.'], function (): void {
            Route::get('/', [AdminManageController::class, 'index'])->name('index');
            Route::get('/{advert}/edit', [AdminManageController::class, 'edit'])->name('edit');
            Route::put('/{advert}/edit', [AdminManageController::class, 'update'])->name('update');   // do not change url -> we do not need specify route in form's action attribute
            Route::post('/{advert}/activate', [AdminManageController::class, 'activate'])->name('activate');    // todo: why post not put?
            Route::get('/{advert}/reject', [AdminManageController::class, 'rejectForm'])->name('reject');
            Route::post('/{advert}/reject', [AdminManageController::class, 'reject']);      // todo: why post not put?
            Route::delete('/{advert}/destroy', [AdminManageController::class, 'destroy'])->name('destroy');
        });
    });

    // Banners routes
    Route::group(['prefix' => 'banners', 'as' => 'banners.'], function (): void {
        Route::get('/', [AdminBannerController::class, 'index'])->name('index');
        Route::get('/{banner}/show', [AdminBannerController::class, 'show'])->name('show');
        Route::get('/{banner}/edit', [AdminBannerController::class, 'editForm'])->name('edit');
        Route::put('/{banner}/edit', [AdminBannerController::class, 'edit']);
        Route::post('/{banner}/moderate', [AdminBannerController::class, 'moderate'])->name('moderate');
        Route::get('/{banner}/reject', [AdminBannerController::class, 'rejectForm'])->name('reject');
        Route::post('/{banner}/reject', [AdminBannerController::class, 'reject']);
        Route::post('/{banner}/pay', [AdminBannerController::class, 'pay'])->name('pay');
        Route::delete('/{banner}/destroy', [AdminBannerController::class, 'destroy'])->name('destroy');
    });

    // Tickets routes
    Route::group(['prefix' => 'tickets', 'as' => 'tickets.'], function (): void {
        Route::get('/', [AdminTicketController::class, 'index'])->name('index');
        Route::get('/{ticket}/show', [AdminTicketController::class, 'show'])->name('show');
        Route::post('{ticket}/add-message', [AdminTicketController::class, 'addMessage'])->name('add_message');
        Route::post('/{ticket}/close', [AdminTicketController::class, 'close'])->name('close');
        Route::post('/{ticket}/approve', [AdminTicketController::class, 'approve'])->name('approve');
        Route::post('/{ticket}/reopen', [AdminTicketController::class, 'reopen'])->name('reopen');
        Route::delete('/{ticket}/destroy', [AdminTicketController::class, 'destroy'])->name('destroy');
    });

    // Users routes
    Route::group(['prefix' => 'users', 'as' => 'users.'], function (): void {
        Route::post('/import', [UserController::class, 'import'])->name('import');
        Route::post('/{user}/verify-email', [UserController::class, 'verifyEmail'])->name('verify_email');
    });
    Route::resource('users', UserController::class);
});

// Route::get('/test', [TestController::class, 'test']);

// Route::get('/test-view', function (Request $request) {
//     // To set a cookie, you need to return a response with the cookie
//     return response()->view('test')->cookie(
//         'example_cookie',
//         'example_value',
//         1 // 1 minutes
//     );
// });

// Route::get('/test-view', function (Request $request) {
//     $response = response()->view('test')
//         ->cookie(
//             'example_cookie',
//             'example_value',
//             1 // 1 minutes
//         );

//     \Log::debug('Response headers:', $response->headers->all());

//     return $response;
// });

// a simple test to ensure the Elasticsearch client is working properly
// Route::get('/test-elasticsearch', function () {
//     // $client = app('Elasticsearch');
//     // Ping the Elasticsearch server
//     // $response = $client->ping();
//     // return $response ? 'Elasticsearch is working!' : 'Elasticsearch is not responding.';
//     if (is_elasticsearch_running()) {
//         return response()->json(['success' => 'Elasticsearch service is working!'], 200); // ok
//     }

//     return response()->json(['error' => 'Elasticsearch is not responding.'], 503); // ok
// });

// where('page_path', '.+') - with this regex ads2.test/api DOES NOT work (404 not found) -> conflict with Route::get('/{page_path}'
// https://stackoverflow.com/questions/46298959/laravel-api-route-conflict-with-web-root
// Route::get('/{page_path}', [PageController::class, 'show'])->name('page')->where('page_path', '.+');
// Route::get('/page/{page_path}', [PageController::class, 'show'])->name('page')->where('page_path', '.+');
Route::get('/{page_path}', [PageController::class, 'show'])->name('page')->where('page_path', '^(?!api).*$');
