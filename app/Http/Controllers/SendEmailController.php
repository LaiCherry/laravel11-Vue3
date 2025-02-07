<?php

namespace App\Http\Controllers;
use App\Mail\sendMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class SendEmailController extends Controller
{
    /**
     * 寄註冊開通信
     *
     * @return \Illuminate\Http\Response
     */
    public function AccountOpenEmail($tomail,$uid,$utick)
    {
        $url = request()->root()."/register/check?uid=".trim($uid)."&tick=".trim($utick);
        // dd($url);
        $data = ['url' => $url, 'title' => '', 'subject' => 'xxxx服務平台登入 信箱開通確認信', 'view' => 'emails.AccountOpen', 'uid' => $uid, 'tick' => $utick];
        Mail::to($tomail)->send(new sendMail($data));
        return redirect()->back();
    }

    /**
     * 寄開通通知信給管理員(user_type=2)
     */
    public function OpenEmailAlert($userid,$userObj)
    {
        $managerEmail = User::where('user_status', '=', true)->where('user_type', '=', '2')->get('email');
        if($managerEmail->toArray()){
            $data = ['title' => '', 'subject' => 'xxxx服務平台登入 使用者帳號開通通知信', 'view' => 'emails.ManageUserOpen', 'userObj' => $userObj];
            Mail::to($managerEmail->toArray())->send(new sendMail($data));
        }
        return redirect()->back();
    }
}
