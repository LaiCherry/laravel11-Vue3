<?php

namespace App\Http\Controllers;
use App\Mail\sendMail;
use Validator;
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
        $data = ['url' => $url, 'title' => '', 'subject' => '影像油污辨識雲端分析服務平台登入 信箱開通確認信', 'view' => 'emails.AccountOpen', 'uid' => $uid, 'tick' => $utick];
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
            $data = ['title' => '', 'subject' => '影像油污辨識雲端分析服務平台登入 使用者帳號開通通知信', 'view' => 'emails.ManageUserOpen', 'userObj' => $userObj];
            Mail::to($managerEmail->toArray())->send(new sendMail($data));
        }
        return redirect()->back();
    }

    /**
     * 使用者忘記密碼，寄送email驗證碼
     */
    public function SendVcode($useremail,$token)
    {
        $data = [ 'title' => '', 'subject' => '影像油污辨識雲端分析服務平台 忘記密碼驗證信', 'view' => 'emails.ForgetPasswordVCode', 'vcode' => $token];
        Mail::to($useremail)->send(new sendMail($data));
        return redirect()->back();
    }

    /**
     * 
     */
    public function SendnewPassword($useremail,$newpwd)
    {
        $data = ['title' => '', 'subject' => '影像油污辨識雲端分析服務平台 新密碼通知信', 'view' => 'emails.NewPassword', 'email' => $useremail, 'pwd' => $newpwd];
        Mail::to($useremail)->send(new sendMail($data));
        return redirect()->back();
    }
}
