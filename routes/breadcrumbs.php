<?php

declare(strict_types=1);

// https://packagist.org/packages/diglactic/laravel-breadcrumbs
// Note: Laravel will automatically resolve `Breadcrumbs::` without
// this import. This is nice for IDE syntax and refactoring.

use App\Http\Router\AdvertsPath;
use App\Http\Router\PagePath;
use App\Models\Action\Action;
use App\Models\Adverts\Advert\Advert;
use App\Models\Adverts\Advert\Dialog\Dialog;
use App\Models\Adverts\Attribute;
use App\Models\Adverts\Category;
// use App\Models\Adverts\Option;
use App\Models\Banner\Banner;
use App\Models\Page;
use App\Models\Region;
use App\Models\Ticket\Ticket;
use App\Models\User\User;
use Diglactic\Breadcrumbs\Breadcrumbs;
// This import is also not required, and you could replace `BreadcrumbTrail $trail`
//  with `$trail`. This is nice for IDE type checking and completion.
use Diglactic\Breadcrumbs\Generator as BreadcrumbTrail;

/**
 * Breadcrumbs::for() - vendor\diglactic\laravel-breadcrumbs\src\Manager.php
 * Register a breadcrumb-generating callback for a page.
 *
 * @param  string  $name  The name of the page.
 * @param  callable  $callback  The callback, which should accept a Generator instance as the first parameter and may
 *                              accept additional parameters.
 * @return void
 */

// Home
Breadcrumbs::for('home', function (BreadcrumbTrail $trail): void {
    $trail->push('Home', route('home'));
});

// Auth =========================================================================

// Home > Login
Breadcrumbs::for('login', function (BreadcrumbTrail $trail): void {
    $trail->parent('home');
    $trail->push('Login', route('login'));
});

// Home > Login > SMS code
Breadcrumbs::for('login.phone', function (BreadcrumbTrail $trail): void {
    $trail->parent('login');
    $trail->push('SMS code', route('login.phone'));
});

// Home > Register
Breadcrumbs::for('register', function (BreadcrumbTrail $trail): void {
    $trail->parent('home');
    $trail->push('Register', route('register'));
});

// Home > Login > Reset Password
Breadcrumbs::for('password.request', function (BreadcrumbTrail $trail): void {
    $trail->parent('login');
    $trail->push('Reset Password', route('password.request'));
});

// Home >  Login > Reset Password > Change
Breadcrumbs::for('password.reset', function (BreadcrumbTrail $trail): void {
    $trail->parent('password.request');
    $trail->push('Reset password', route('password.reset', ['token' => 'a-token-string']));
});

Breadcrumbs::for('verification.notice', function (BreadcrumbTrail $trail): void {
    $trail->parent('home');
    $trail->push('Email verification', route('verification.notice', ['token' => 'aaaa']));
});

// Public =========================================================================

Breadcrumbs::for('page', function (BreadcrumbTrail $trail, PagePath $path): void {
    if ($parent = $path->page->parent) {
        $trail->parent('page', $path->withPage($path->page->parent));
    } else {
        $trail->parent('home');
    }
    $trail->push($path->page->title, route('page', $path));
});

// Public Adverts === === === === === === === ===
// Lesson 8 - 01:28:30 - explanation
Breadcrumbs::for('adverts.inner_category', function (BreadcrumbTrail $trail, AdvertsPath $path): void {
    if ($path->category && $parent = $path->category->parent) {
        $trail->parent('adverts.inner_category', $path->withCategory($parent));
    } else {
        $trail->parent('home');
        $trail->push('Adverts Categories', route('adverts.index'));
    }
    if ($path->category) {
        $trail->push($path->category->name, route('adverts.index', $path->withoutRegion()));
    }
});

Breadcrumbs::for('adverts.inner_region', function (BreadcrumbTrail $trail, AdvertsPath $path, AdvertsPath $orig): void {
    if ($path->region && $parent = $path->region->parent) {
        $trail->parent('adverts.inner_region', $path->withRegion($parent), $orig);
    } else {
        // When is it running?
        $trail->parent('adverts.inner_category', $orig);
    }
    if ($path->region) {
        $trail->push($path->region->name, route('adverts.index', $path));
    }
});

Breadcrumbs::for('adverts.index', function (BreadcrumbTrail $trail, ?AdvertsPath $path = null): void {
    // The ternary operator, but without the second operand.
    // If the operand is valid, it returns the first operand; otherwise, it evaluates and returns the second operand
    $path = $path ?: adverts_path(null, null);
    $trail->parent('adverts.inner_region', $path, $path);
});

Breadcrumbs::for('adverts.show', function (BreadcrumbTrail $trail, Advert $advert): void {
    // BUG fixed: without refreshing $advert->category->ancestors collection is empty
    // so breadcrumbs returned without ancestors categories !!!
    // workaround: use refresh(), but it generates one extra query
    // $advert->category->refresh();
    // dump($advert->category->ancestors); // Debug here to ensure ancestors are loaded
    // fixed: the reason was in app\Http\Controllers\AdvertController.php show()
    // $advert = $advert->loadMissing([
    // 'category:id,name', - result is bug load whole category -> 'category' !!!

    // Set the parent breadcrumb to the 'adverts.index' route,
    // providing the path to the adverts index view along with the category and region of the advert.
    $trail->parent('adverts.index', adverts_path($advert->category, $advert->region));

    // Add a breadcrumb for the specific advert being shown.
    // The breadcrumb's label is the advert's title excerpt,
    // and it links to the advert's detailed view using its unique route.
    $trail->push($advert->title_excerpt, route('adverts.show', $advert));
});

Breadcrumbs::for('adverts.search', function (BreadcrumbTrail $trail): void {
    $trail->parent('home');
    $trail->push('Search', route('adverts.search'));
});

// Cabinet =========================================================================

// Cabinet Profile === === === === === === === ===

// Home > Cabinet > Profile
Breadcrumbs::for('cabinet.profile.show', function (BreadcrumbTrail $trail): void {
    $trail->parent('home');
    $trail->push('Profile', route('cabinet.profile.show'));
});

// Home > Cabinet > Edit Profile
Breadcrumbs::for('cabinet.profile.edit', function (BreadcrumbTrail $trail): void {
    $trail->parent('cabinet.profile.show');
    $trail->push('Edit Profile', route('cabinet.profile.edit'));
});

// Home > Cabinet > Phone
Breadcrumbs::for('cabinet.profile.phone.show_verification_token_form', function (BreadcrumbTrail $trail): void {
    $trail->parent('cabinet.profile.show');
    $trail->push('Phone', route('cabinet.profile.phone.show_verification_token_form'));
});

// Cabinet Adverts === === === === === === === ===

// Home > Cabinet >  Adverts
Breadcrumbs::for('cabinet.adverts.index', function (BreadcrumbTrail $trail): void {
    $trail->parent('home');
    $trail->push('My Adverts', route('cabinet.adverts.index'));
});

Breadcrumbs::for('cabinet.adverts.create.select_root_category', function (BreadcrumbTrail $trail): void {
    $trail->parent('cabinet.adverts.index');
    $trail->push('New Advert', route('cabinet.adverts.create.select_root_category'));
});

Breadcrumbs::for('cabinet.adverts.create.select_region', function (BreadcrumbTrail $trail, Category $category, ?Region $region = null): void {
    $trail->parent('cabinet.adverts.index');
    $trail->push('New Advert', route('cabinet.adverts.create.select_region', [$category, $region]));
});

Breadcrumbs::for('cabinet.adverts.create.select_action', function (BreadcrumbTrail $trail, Category $category, Region $region): void {
    $trail->parent('cabinet.adverts.index');
    $trail->push('New Advert', route('cabinet.adverts.create.select_action', [$category, $region]));
});

Breadcrumbs::for('cabinet.adverts.create', function (BreadcrumbTrail $trail, Category $category, ?Region $region = null): void {
    $trail->parent('cabinet.adverts.create.select_region', $category, $region);
    $trail->push($region ? $region->name : 'All', route('cabinet.adverts.create', [$category, $region]));
});

// Home > Cabinet > Adverts > Advert Path Recrusion > Edit Advert
Breadcrumbs::for('cabinet.adverts.edit', function (BreadcrumbTrail $trail, Advert $advert): void {
    // Set the parent breadcrumb for the 'adverts.show' route.
    // The 'adverts.show' breadcrumb dynamically builds the advert's path by recursively traversing
    // both the categories tree (category hierarchy) and regions tree (region hierarchy).
    // This means the breadcrumb will reflect the full path leading to the advert based on its category and region.
    $trail->parent('adverts.show', $advert);

    // Add the current page (Edit Advert) as the final breadcrumb, pointing to the advert's edit page.
    // The link to the edit page will be generated for the specific advert passed in.
    $trail->push('Edit Advert', route('cabinet.adverts.edit', $advert));
});

// Cabinet Favorites === === === === === === === ===

Breadcrumbs::for('cabinet.favorites.index', function (BreadcrumbTrail $trail): void {
    $trail->parent('home');
    $trail->push('My Favorites', route('cabinet.favorites.index'));
});

// Cabinet Banners === === === === === === === ===

Breadcrumbs::for('cabinet.banners.index', function (BreadcrumbTrail $trail): void {
    $trail->parent('home');
    $trail->push('Banners', route('cabinet.banners.index'));
});

Breadcrumbs::for('cabinet.banners.show', function (BreadcrumbTrail $trail, Banner $banner): void {
    $trail->parent('cabinet.banners.index');
    $trail->push($banner->name, route('cabinet.banners.show', $banner));
});

Breadcrumbs::for('cabinet.banners.edit', function (BreadcrumbTrail $trail, Banner $banner): void {
    $trail->parent('cabinet.banners.show', $banner);
    $trail->push('Edit Banner', route('cabinet.banners.edit', $banner));
});

Breadcrumbs::for('cabinet.banners.file', function (BreadcrumbTrail $trail, Banner $banner): void {
    $trail->parent('cabinet.banners.show', $banner);
    $trail->push('File', route('cabinet.banners.file', $banner));
});

Breadcrumbs::for('cabinet.banners.create', function (BreadcrumbTrail $trail): void {
    $trail->parent('cabinet.banners.index');
    $trail->push('Add New Banner', route('cabinet.banners.create'));
});

Breadcrumbs::for('cabinet.banners.create.region', function (BreadcrumbTrail $trail, Category $category, ?Region $region = null): void {
    $trail->parent('cabinet.banners.create');
    $trail->push($category->name, route('cabinet.banners.create.region', [$category, $region]));
});

Breadcrumbs::for('cabinet.banners.create.banner', function (BreadcrumbTrail $trail, Category $category, ?Region $region = null): void {
    $trail->parent('cabinet.banners.create.region', $category, $region);
    $trail->push($region ? $region->name : 'All', route('cabinet.banners.create.banner', [$category, $region]));
});

// Cabinet Tickets === === === === === === === ===

Breadcrumbs::for('cabinet.tickets.index', function (BreadcrumbTrail $trail): void {
    $trail->parent('home');
    $trail->push('Tickets', route('cabinet.tickets.index'));
});

Breadcrumbs::for('cabinet.tickets.create', function (BreadcrumbTrail $trail): void {
    $trail->parent('cabinet.tickets.index');
    $trail->push('Create New Ticket', route('cabinet.tickets.create'));
});

Breadcrumbs::for('cabinet.tickets.show', function (BreadcrumbTrail $trail, Ticket $ticket): void {
    $trail->parent('cabinet.tickets.index');
    $trail->push($ticket->subject, route('cabinet.tickets.show', $ticket));
});

Breadcrumbs::for('cabinet.tickets.edit', function (BreadcrumbTrail $trail, Ticket $ticket): void {
    $trail->parent('cabinet.tickets.show', $ticket);
    $trail->push('Edit Ticket', route('cabinet.tickets.edit', $ticket));
});

// Cabinet Dialogs === === === === === === === ===

Breadcrumbs::for('cabinet.adverts.my_dialogs.index', function (BreadcrumbTrail $trail): void {
    $trail->parent('home');
    $trail->push('My Dialogs', route('cabinet.adverts.my_dialogs.index'));
});

Breadcrumbs::for('cabinet.adverts.dialogs.index', function (BreadcrumbTrail $trail, Advert $advert): void {
    $trail->parent('adverts.show', $advert);
    $trail->push('Dialogs', route('cabinet.adverts.dialogs.index', [$advert]));
});

Breadcrumbs::for('cabinet.adverts.dialogs.create', function (BreadcrumbTrail $trail, Advert $advert): void {
    $trail->parent('adverts.show', $advert);
    $trail->push('New Dialog', route('cabinet.adverts.dialogs.create', [$advert]));
});

Breadcrumbs::for('cabinet.dialogs.messages.create', function (BreadcrumbTrail $trail, Dialog $dialog): void {
    $trail->parent('adverts.show', $dialog->advert);
    $trail->push('New Message', route('cabinet.dialogs.messages.create', [$dialog]));
});

Breadcrumbs::for('cabinet.dialogs.show', function (BreadcrumbTrail $trail, Dialog $dialog): void {
    $trail->parent('adverts.show', $dialog->advert);
    $trail->push('Dialog with: ' . $dialog->counterpart->name, route('cabinet.dialogs.show', [$dialog]));
});

// Admin =========================================================================

// Home > Admin
Breadcrumbs::for('admin.dashboard', function (BreadcrumbTrail $trail): void {
    $trail->parent('home');
    $trail->push('Admin', route('admin.dashboard'));
});

// Admin Users === === === === === === === ===

// Home > Admin > Users
Breadcrumbs::for('admin.users.index', function (BreadcrumbTrail $trail): void {
    $trail->parent('admin.dashboard');
    $trail->push('Users', route('admin.users.index'));
});

// Home > Admin > Users > New user
Breadcrumbs::for('admin.users.create', function (BreadcrumbTrail $trail): void {
    $trail->parent('admin.users.index');
    $trail->push('New user', route('admin.users.create'));
});

// Home > Admin > Users > User name
Breadcrumbs::for('admin.users.show', function (BreadcrumbTrail $trail, User $user): void {
    $trail->parent('admin.users.index');
    $trail->push("User: {$user->name}", route('admin.users.show', $user));
});

// Home > Admin > Users > User name > Edit User
Breadcrumbs::for('admin.users.edit', function (BreadcrumbTrail $trail, User $user): void {
    $trail->parent('admin.users.show', $user);
    $trail->push('Edit User', route('admin.users.edit', $user));
});

// Admin Regions === === === === === === === ===

// Home > Admin > Regions
Breadcrumbs::for('admin.regions.index', function (BreadcrumbTrail $trail): void {
    $trail->parent('admin.dashboard');
    $trail->push('Regions', route('admin.regions.index'));
});

// Home > Admin > Regions > Add New region
Breadcrumbs::for('admin.regions.create', function (BreadcrumbTrail $trail): void {
    $trail->parent('admin.regions.index');
    $trail->push('Add New Region', route('admin.regions.create'));
});

// Home > Admin > Regions' Recrussion
Breadcrumbs::for('admin.regions.show', function (BreadcrumbTrail $trail, Region $region): void {
    if ($parent = $region->parent) {
        $trail->parent('admin.regions.show', $parent);
    } else {
        $trail->parent('admin.regions.index');
    }
    $trail->push($region->name, route('admin.regions.show', $region));
});

// Home > Admin > Regions' Recrussion > Edit Region
Breadcrumbs::for('admin.regions.edit', function (BreadcrumbTrail $trail, Region $region): void {
    $trail->parent('admin.regions.show', $region);
    $trail->push('Edit Region', route('admin.regions.edit', $region));
});

// Admin Advert Categories === === === === === === === ===

// Home > Admin > Adverts Categories
Breadcrumbs::for('admin.adverts.categories.index', function (BreadcrumbTrail $trail): void {
    $trail->parent('admin.dashboard');
    $trail->push('Adverts Categories', route('admin.adverts.categories.index'));
});

Breadcrumbs::for('admin.adverts.categories.subcats.index', function (BreadcrumbTrail $trail, Category $category): void {
    // $trail->parent('admin.dashboard');
    // $trail->push('Adverts Categories', route('admin.adverts.categories.subcats.index', $category));
    /**
     * parent() = Relation to the parent.
     *
     * @return BelongsTo
     *                   vendor\kalnoy\nestedset\src\NodeTrait.php
     */
    if ($parent = $category->parent) {
        $trail->parent('admin.adverts.categories.subcats.index', $parent);
    } else {
        $trail->parent('admin.adverts.categories.index');
    }
    $trail->push($category->name, route('admin.adverts.categories.subcats.index', $category));
});

// Home > Admin > Categories > Add Root Category
Breadcrumbs::for('admin.adverts.categories.create', function (BreadcrumbTrail $trail): void {
    $trail->parent('admin.adverts.categories.index');
    $trail->push('Add Root Category', route('admin.adverts.categories.create'));
});

// Home > Admin > Categories' Recrussion
Breadcrumbs::for('admin.adverts.categories.show', function (BreadcrumbTrail $trail, Category $category): void {
    /**
     * parent() = Relation to the parent.
     *
     * @return BelongsTo
     *                   vendor\kalnoy\nestedset\src\NodeTrait.php
     */
    if ($parent = $category->parent) {
        $trail->parent('admin.adverts.categories.subcats.index', $parent);
    } else {
        $trail->parent('admin.adverts.categories.index');
    }
    $trail->push($category->name, route('admin.adverts.categories.show', $category));
});

// Home > Admin > Categories' Recrussion > Edit Category
Breadcrumbs::for('admin.adverts.categories.edit', function (BreadcrumbTrail $trail, Category $category): void {
    $trail->parent('admin.adverts.categories.subcats.index', $category);
    $trail->push('Edit Category', route('admin.adverts.categories.edit', $category));
});

// Home > Admin > Categories' Recrussion > Add New subCategory
Breadcrumbs::for('admin.adverts.categories.subcats.create', function (BreadcrumbTrail $trail, Category $category): void {
    $trail->parent('admin.adverts.categories.subcats.index', $category);
    $trail->push('Add New subCategory', route('admin.adverts.categories.subcats.create', $category));
});

// Admin Advert Category Attributes === === === === === === === ===

// Home > Admin > Categories > Categories' Recrussion > Add New Attribute
Breadcrumbs::for('admin.adverts.categories.attributes.create', function (
    BreadcrumbTrail $trail,
    Category $category
): void {
    $trail->parent('admin.adverts.categories.show', $category);
    $trail->push('Add New Attribute', route('admin.adverts.categories.attributes.create', $category));
});

Breadcrumbs::for('admin.adverts.categories.attributes.show', function (
    BreadcrumbTrail $trail,
    Category $category,
    Attribute $attribute
): void {
    $trail->parent('admin.adverts.categories.show', $category);
    $trail->push('Attribute: ' . $attribute->name, route('admin.adverts.categories.attributes.show', [$category, $attribute]));
});

Breadcrumbs::for('admin.adverts.categories.attributes.edit', function (
    BreadcrumbTrail $trail,
    Category $category,
    Attribute $attribute
): void {
    $trail->parent('admin.adverts.categories.attributes.show', $category, $attribute);
    $trail->push('Edit Attribute', route('admin.adverts.categories.attributes.edit', [$category, $attribute]));
});

// Home > Admin > Categories > Categories' Recrussion > Attributes' Settings
Breadcrumbs::for('admin.adverts.categories.all_attributes.settings.create', function (
    BreadcrumbTrail $trail,
    Category $category
): void {
    $trail->parent('admin.adverts.categories.show', $category);
    $trail->push('Attributes Settings', route('admin.adverts.categories.all_attributes.settings.create', $category));
});

// Admin Advert Category Actions === === === === === === === ===

// Home > Admin > Categories > Categories' Recrussion > Select Actions
Breadcrumbs::for('admin.adverts.categories.actions.create', function (
    BreadcrumbTrail $trail,
    Category $category
): void {
    $trail->parent('admin.adverts.categories.show', $category);
    $trail->push('Select Actions', route('admin.adverts.categories.actions.create', $category));
});

// Admin Adverts === === === === === === === ===

Breadcrumbs::for('admin.adverts.manage.index', function (BreadcrumbTrail $trail): void {
    $trail->parent('admin.dashboard');
    $trail->push('Adverts', route('admin.adverts.manage.index'));
});

Breadcrumbs::for('admin.adverts.manage.edit', function (BreadcrumbTrail $trail, Advert $advert): void {
    $trail->parent('admin.dashboard');
    $trail->push($advert->title, route('admin.adverts.manage.edit', $advert));
});

Breadcrumbs::for('admin.adverts.manage.reject', function (BreadcrumbTrail $trail, Advert $advert): void {
    $trail->parent('admin.dashboard');
    $trail->push($advert->title, route('admin.adverts.manage.reject', $advert));
});

// Admin Banners === === === === === === === ===

Breadcrumbs::for('admin.banners.index', function (BreadcrumbTrail $trail): void {
    $trail->parent('admin.dashboard');
    $trail->push('Banners', route('admin.banners.index'));
});

Breadcrumbs::for('admin.banners.show', function (BreadcrumbTrail $trail, Banner $banner): void {
    $trail->parent('admin.banners.index');
    $trail->push($banner->name, route('admin.banners.show', $banner));
});

Breadcrumbs::for('admin.banners.edit', function (BreadcrumbTrail $trail, Banner $banner): void {
    $trail->parent('admin.banners.show', $banner);
    $trail->push('Edit Banner', route('admin.banners.edit', $banner));
});

Breadcrumbs::for('admin.banners.reject', function (BreadcrumbTrail $trail, Banner $banner): void {
    $trail->parent('admin.banners.show', $banner);
    $trail->push('Reject', route('admin.banners.reject', $banner));
});

// Admin Actions === === === === === === === ===

Breadcrumbs::for('admin.actions.index', function (BreadcrumbTrail $trail): void {
    $trail->parent('admin.dashboard');
    $trail->push('Actions', route('admin.actions.index'));
});

Breadcrumbs::for('admin.actions.create', function (BreadcrumbTrail $trail): void {
    $trail->parent('admin.actions.index');
    $trail->push('Add New Action', route('admin.actions.create'));
});

Breadcrumbs::for('admin.actions.edit', function (BreadcrumbTrail $trail, Action $action): void {
    $trail->parent('admin.actions.index');
    $trail->push('Edit Action', route('admin.actions.edit', $action));
});

// Admin Pages === === === === === === === ===

Breadcrumbs::for('admin.pages.index', function (BreadcrumbTrail $trail): void {
    $trail->parent('admin.dashboard');
    $trail->push('Pages', route('admin.pages.index'));
});

Breadcrumbs::for('admin.pages.create', function (BreadcrumbTrail $trail): void {
    $trail->parent('admin.pages.index');
    $trail->push('Add New Page', route('admin.pages.create'));
});

Breadcrumbs::for('admin.pages.show', function (BreadcrumbTrail $trail, Page $page): void {
    if ($parent = $page->parent) {
        $trail->parent('admin.pages.show', $parent);
    } else {
        $trail->parent('admin.pages.index');
    }
    $trail->push($page->title, route('admin.pages.show', $page));
});

Breadcrumbs::for('admin.pages.edit', function (BreadcrumbTrail $trail, Page $page): void {
    $trail->parent('admin.pages.show', $page);
    $trail->push('Edit Page', route('admin.pages.edit', $page));
});

// Admin tickets === === === === === === === ===

Breadcrumbs::for('admin.tickets.index', function (BreadcrumbTrail $trail): void {
    $trail->parent('admin.dashboard');
    $trail->push('Tickets', route('admin.tickets.index'));
});

Breadcrumbs::for('admin.tickets.show', function (BreadcrumbTrail $trail, Ticket $ticket): void {
    $trail->parent('admin.tickets.index');
    $trail->push($ticket->subject, route('admin.tickets.show', $ticket));
});
