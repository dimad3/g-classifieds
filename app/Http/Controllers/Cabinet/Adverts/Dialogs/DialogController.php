<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cabinet\Adverts\Dialogs;

use App\Http\Controllers\Controller;
use App\Http\Requests\Adverts\Dialog\MessageRequest;
use App\Models\Adverts\Advert\Advert;
use App\Models\Adverts\Advert\Dialog\Dialog;
use Illuminate\Support\Facades\Gate;

class DialogController extends Controller
{
    public function __construct()
    {
        // todo: why admin middlewre works, but cabinet not?
        // because admin middlewre does not need second parameter
        // $this->middleware('can:manage-own-dialog');
    }

    public function index(Advert $advert)
    {
        $dialogs = Dialog::forAdvert($advert)
            ->forUser()
            ->orderByDesc('updated_at')
            ->with(['owner:id,name', 'client:id,name'])
            ->withCount(['messagesReceived', 'messagesSent'])
            ->paginate(20);

        // dd($dialogs);
        return view('cabinet.dialogs.index', compact('advert', 'dialogs'));
    }

    public function create(Advert $advert)
    {
        $dialog = new Dialog();

        return view('cabinet.dialogs.show', compact('advert', 'dialog'));
    }

    public function store(MessageRequest $request, Advert $advert)
    {
        try {
            // Only clients can create new dialog
            $dialog = $advert->writeClientMessage(auth()->user()?->id, $request['message']);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('cabinet.dialogs.show', [$dialog]);
    }

    public function show(Dialog $dialog)
    {
        $this->checkAccess($dialog);

        $advert = $dialog->advert;
        $messages = $dialog->messages()->orderBy('created_at')->with('user')->get();

        $messagesByDate = $messages->groupBy(function ($item) {
            return $item->created_at->format('d.m.Y');
        });

        if ($dialog->loggedInUserIsOwner()) {
            $dialog->readByOwner();
        } else {
            $dialog->readByClient();
        }

        return view('cabinet.dialogs.show', compact('advert', 'dialog', 'messagesByDate'));
    }

    /**
     * Remove dialogand all its messages
     */
    public function destroy(Dialog $dialog)
    {
        $this->checkAccess($dialog);

        try {
            $advert = $dialog->advert;
            $dialog->delete();
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('cabinet.adverts.dialogs.index', [$advert]);
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
