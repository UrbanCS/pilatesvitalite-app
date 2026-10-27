<?php

namespace Modules\NewsLetter\app\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Member;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\NewsLetter\app\Models\MailingList;
use Modules\NewsLetter\app\Models\NewsLetter;

class MailingListController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:admin');
    }

    public function index()
    {
        checkAdminHasPermissionAndThrowException('mailing_list.view');
        $mailingLists = MailingList::withCount('subscribers')->orderBy('id', 'desc')->get();

        return view('newsletter::mailing_list_index', compact('mailingLists'));
    }

    public function create()
    {
        checkAdminHasPermissionAndThrowException('mailing_list.create');

        return view('newsletter::mailing_list_create');
    }

    public function store(Request $request)
    {
        checkAdminHasPermissionAndThrowException('mailing_list.create');
        $validated = $request->validate([
            'name' => 'required|unique:mailing_lists,name|max:255',
            'description' => 'nullable',
        ]);

        MailingList::create($validated);

        $notification = ['message' => __('Mailing list created successfully'), 'alert-type' => 'success'];
        return redirect()->route('admin.mailing-list-index')->with($notification);
    }

    public function edit(MailingList $list)
    {
        checkAdminHasPermissionAndThrowException('mailing_list.edit');

        return view('newsletter::mailing_list_edit', ['mailingList' => $list]);
    }

    public function update(Request $request, MailingList $list)
    {
        checkAdminHasPermissionAndThrowException('mailing_list.edit');
        $validated = $request->validate([
            'name' => 'required|unique:mailing_lists,name,' . $list->id . '|max:255',
            'description' => 'nullable',
        ]);

        $list->update($validated);

        $notification = ['message' => __('Mailing list updated successfully'), 'alert-type' => 'success'];
        return redirect()->route('admin.mailing-list-index')->with($notification);
    }

    public function destroy(MailingList $list)
    {
        checkAdminHasPermissionAndThrowException('mailing_list.delete');
        $list->delete();

        $notification = ['message' => __('Mailing list deleted successfully'), 'alert-type' => 'success'];
        return redirect()->back()->with($notification);
    }

    public function manageSubscribers(MailingList $list)
    {
        checkAdminHasPermissionAndThrowException('mailing_list.manage');

        $availableSubscribers = NewsLetter::where('status', 'verified')
            ->whereDoesntHave('mailingLists', function ($query) use ($list) {
                $query->where('mailing_list_id', $list->id);
            })
            ->orderBy('email')
            ->get();

        $members = Member::with('user')
            ->where('status', 1)
            ->whereHas('user', function ($query) {
                $query->whereNotNull('email')->where('email', '!=', '');
            })
            ->orderBy('id', 'desc')
            ->get();

        return view('newsletter::mailing_list_manage_subscribers', [
            'mailingList' => $list->load('subscribers'),
            'availableSubscribers' => $availableSubscribers,
            'members' => $members,
        ]);
    }

    public function addSubscribers(Request $request, MailingList $list)
    {
        checkAdminHasPermissionAndThrowException('mailing_list.manage');
        $validated = $request->validate([
            'subscriber_ids' => 'required|array',
            'subscriber_ids.*' => 'exists:news_letters,id',
        ]);

        $list->subscribers()->syncWithoutDetaching($validated['subscriber_ids']);

        $notification = ['message' => __('Subscribers added successfully'), 'alert-type' => 'success'];
        return redirect()->back()->with($notification);
    }

    public function importMembers(Request $request, MailingList $list)
    {
        checkAdminHasPermissionAndThrowException('mailing_list.manage');
        $validated = $request->validate([
            'member_ids' => 'required|array',
            'member_ids.*' => 'exists:members,id',
        ]);

        $members = Member::with('user')->whereIn('id', $validated['member_ids'])->get();
        $imported = 0;
        $skipped = 0;

        foreach ($members as $member) {
            $email = strtolower(trim((string) optional($member->user)->email));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $skipped++;
                continue;
            }

            $subscriber = NewsLetter::whereRaw('LOWER(email) = ?', [$email])->first();
            if ($subscriber && $subscriber->status !== 'verified') {
                $skipped++;
                continue;
            }

            if (!$subscriber) {
                $subscriber = NewsLetter::create([
                    'email' => $email,
                    'status' => 'verified',
                    'verify_token' => Str::random(100),
                ]);
            } elseif (!$subscriber->verify_token) {
                $subscriber->update(['verify_token' => Str::random(100)]);
            }

            if (!$list->subscribers()->where('news_letters.id', $subscriber->id)->exists()) {
                $list->subscribers()->attach($subscriber->id);
                $imported++;
            }
        }

        $message = $imported . ' membre(s) importé(s).';
        if ($skipped > 0) {
            $message .= ' ' . $skipped . ' ignoré(s) (courriel invalide ou désabonné/non vérifié).';
        }

        return redirect()->back()->with(['message' => $message, 'alert-type' => 'success']);
    }

    public function removeSubscriber(Request $request, MailingList $list)
    {
        checkAdminHasPermissionAndThrowException('mailing_list.manage');
        $validated = $request->validate([
            'subscriber_id' => 'required|exists:news_letters,id',
        ]);

        $list->subscribers()->detach($validated['subscriber_id']);

        $notification = ['message' => __('Subscriber removed successfully'), 'alert-type' => 'success'];
        return redirect()->back()->with($notification);
    }
}
