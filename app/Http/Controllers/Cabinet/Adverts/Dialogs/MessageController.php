<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cabinet\Adverts\Dialogs;

use App\Http\Controllers\Controller;
use App\Http\Requests\Adverts\Dialog\MessageRequest;
use App\Models\Adverts\Advert\Dialog\Dialog;
use Illuminate\Support\Facades\Gate;

class MessageController extends Controller
{
    public function __construct()
    {
        // todo: why admin middlewre works, but cabinet not?
        // because admin middlewre does not need second parameter
        // $this->middleware('can:manage-own-message');
    }

    public function store(MessageRequest $request, Dialog $dialog)
    {
        $this->checkAccess($dialog);
        try {
            $advert = $dialog->advert;
            if ($dialog->loggedInUserIsOwner()) {
                $advert->writeOwnerMessage($dialog->client_id, $request['message']);
            } else {
                $advert->writeClientMessage(auth()->user()?->id, $request['message']);
            }
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->back();
    }

    // HELPERS sub-methods ====================================

    // can not be injected in constructor because this middleware accepts two parameters
    // so the middleware can be called only from method where $dialog argument is accessable
    private function checkAccess(Dialog $dialog): void
    {
        if (! Gate::allows('manage-own-dialog', $dialog)) {
            abort(403);
        }
    }
}
