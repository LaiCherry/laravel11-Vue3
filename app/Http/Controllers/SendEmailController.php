<?php

namespace App\Http\Controllers;
use App\Mail\sendMail;
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
}
