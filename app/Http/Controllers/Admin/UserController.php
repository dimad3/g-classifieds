<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ImportFromFileRequest;
use App\Http\Requests\Admin\UserRequest;
use App\Http\Requests\Admin\UsersIndexRequest;
use App\Imports\UsersImport;
use App\Models\User\User;
use App\Services\Auth\RegisterService;
use Maatwebsite\Excel\Facades\Excel;

class UserController extends Controller
{
    private $registerService;

    public function __construct(RegisterService $registerService)
    {
        $this->registerService = $registerService;
        // $this->middleware('can:manage-users'); // is called twice see "barryvdh/laravel-debugbar": gates. Is applyed also in: resources\views\admin\_nav.blade.php
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function index(UsersIndexRequest $request)
    {
        // Start query building
        $query = User::query();

        // filtering conditions
        if (! empty($value = $request->get('id'))) {
            $query->where('id', $value);
        }

        if (! empty($value = $request->get('name'))) {
            $query->where('name', 'like', '%' . $value . '%');
        }

        if (! empty($value = $request->get('email'))) {
            $query->where('email', 'like', '%' . $value . '%');
        }

        if (! empty($value = $request->get('status'))) {
            $query->where('status', $value);
        }

        if (! empty($value = $request->get('role'))) {
            $query->where('role', $value);
        }

        $users = $query->orderByDesc('id')->paginate(100)->withQueryString(); // Retain query string parameters

        return view('admin.users.index', [
            'users' => $users,
            'statuses' => User::statusesList(),
            'roles' => User::rolesList(),
            'usersCount' => $users->total(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function create()
    {
        return $this->edit(new User());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(UserRequest $request)
    {
        return $this->update($request, new User());
    }

    /**
     * Display the specified resource.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function show(User $user)
    {
        return view('admin.users.show', compact('user'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function edit(User $user)
    {
        $roles = User::rolesList();

        return view('admin.users.create_or_edit', compact('user', 'roles'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UserRequest $request, User $user)
    {
        if ($user->exists) {
            $request->storeOrUpdate($user);
        } else {
            $user = $request->storeOrUpdate($user);
        }

        return redirect()->route('admin.users.show', $user);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        if ($user->adverts()->exists()) {
            return redirect()
                ->back()
                ->with(
                    'error',
                    "You can not delete the user which has adverts!<br>
                    At first delete all adverts which are assigned to this user: [{$user->name}].<br>
                    Then try again."
                );
        }
        $user->delete();

        return redirect()->route('admin.users.index');
    }

    public function verifyEmail(User $user)
    {
        $this->registerService->verifyEmail($user->id);

        return redirect()->route('admin.users.show', $user);
    }

    public function import(ImportFromFileRequest $importFromFileRequest)
    {
        Excel::import(new UsersImport, $importFromFileRequest->file('file'));

        return redirect()->route('admin.users.index')->with('success', 'Users have been imported successfuly!');
    }
}
