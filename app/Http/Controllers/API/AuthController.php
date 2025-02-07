<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Http\Controllers\API\BaseController as BaseController;
use App\Http\Controllers\SendEmailController;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Validator;
use DateTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Crypt;

class AuthController extends BaseController
{
    /** (一般註冊)帳號重複檢查(不存在將return null) */
    public function accountduplicate($checkemail){
        $check = User::where('email', '=', trim($checkemail))->where('group_type','=','Normal')->first();
        return $check;
    }

    /** 寄送Email通知系統管理員進行{{$uid}}帳號開通 */
    public function OpenUserEmailAlert($uid,$userObj){
        try{
            $SendEmailController = new SendEmailController();
            $SendEmailController->OpenEmailAlert($uid,$userObj);
            return true;
        }catch(\Exception $e){
            return false;
        }
    }

    /** 註冊之Email驗證完成與否 */
    public function UserOpen(Request $request){
        try{
            $success = [];
            $validator = Validator::make($request->all(), [
                'uid' => 'required',
                'tick' => 'required',
            ]);
    
            if($validator->fails()){
                return $this->sendError('Validation Error.', $validator->errors());
            }

            $input = $request->all();
            $input['uid'] = decrypt(trim($input['uid']));
            $input['tick'] = trim($input['tick']);

            if($input['uid'] && $input['tick']){
                $user = User::where('id', '=', $input['uid'])->where('tick','=',$input['tick'])->first();
                if($user){
                    // dd($user);
                    if($user->email_verified_at) {
                        // $success['isOpen'] = true;
                        $success['memo'] = '帳號已於'.explode(' ', $user->email_verified_at)[0].'完成Email信件驗證! 尚待系統管理員開通帳號....';  
                        return redirect('/')->with(['alert'=>$success['memo'],'directurl'=>'/']);
                    }else if($user->user_status) {
                        $success['memo'] = '帳號已於'.explode(' ', $user->user_open_at)[0].'完成帳號開通! 系統將跳轉至登入頁，請進行登入。';  
                        return redirect('/')->with(['alert'=>$success['memo'],'directurl'=>'/login']);
                    }else {
                        $user_open = User::where('id', '=', $input['uid'])->where('tick','=', $input['tick'])
                                    ->update([ 'email_verified_at' => now() ]);
                        if($user_open) {
                            if($this->OpenUserEmailAlert($input['uid'],$user)){ // 通知管理員執行帳號開通
                                $success['memo'] = 'Email信件完成驗證! 後續將由系統管理員進行帳號開通，開通完成將進行Email通知...';
                                return redirect('/')->with(['alert'=>$success['memo'],'directurl'=>'/']);
                            }else return redirect('/')->with(['alert'=>'通知系統管理員進行帳號開通失敗! 請洽系統管理員!.','directurl'=>'/']);
                        }else return redirect('/')->with(['alert'=>'Email信件驗證失敗! 請洽系統管理員!.','directurl'=>'/']);
                        // }else return $this->sendError('開通失敗! 請洽系統管理員!.', ['error'=>'開通失敗! 請洽系統管理員!']);
                    }
                }
                else { return redirect('/')->with(['alert'=>'Email信件驗證失敗! 請洽系統管理員!!.','directurl'=>'/']); }
                // else{ return $this->sendError('開通失敗! 請洽系統管理員!!.', ['error'=>'開通失敗! 請洽系統管理員!!']); }
            }
            else {
                return redirect('/')->with(['alert'=>'Email信件驗證失敗! 請洽系統管理員!!!.','directurl'=>'/']);
                // return $this->sendError('開通失敗! 請洽系統管理員!!!.', ['error'=>'開通失敗! 請洽系統管理員!!!']);
            }
        }
        catch (\Exception $e) {
            return redirect('/')->with(['alert'=>'Email信件驗證失敗!! 請洽系統管理員!.','directurl'=>'/']);
            // return $this->sendError('開通失敗!! 請洽系統管理員!.', ['error'=>'開通失敗!! 請洽系統管理員!']);
        }
    }
    
    /**
     * Register api
     *
     * @return \Illuminate\Http\Response
     */
    public function register(Request $request): JsonResponse
    {
        $date = new DateTime();
        $success = [];
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'email' => 'required|email',
            'password' => 'required',
            'passwordcheck' => 'required|same:password',
            'area_type' => 'required'
        ]);

        if($validator->fails()){
            return $this->sendError('Validation Error.', $validator->errors());
        }

        try{
            if($this->accountduplicate($request->input('email'))){
                return $this->sendError('Email帳號已存在，請重新確認!.', ['error'=>'Email帳號已存在，請重新確認!']);
            }
            else{
                $input = $request->all();
                $input['password'] = bcrypt($input['password']);
                $input['account'] = explode("@",$input['email'])[0];
                // $input['account'] = trim($input['name']);
                $input['tick'] = intval(date("Ymd")) . $date->getTimestamp() . rand(77777,88888);
                // dd($input);
                $user = User::create($input);
                $success['token'] =  $user->createToken('MyApp')->plainTextToken;
                $success['name'] =  $user->name;
                $uidencode = encrypt($user->id);
                // dd($user->id,$uidencode);
                $SendEmailController = new SendEmailController();
                $SendEmailController->AccountOpenEmail($user->email,$uidencode,$user->tick);
                return $this->sendResponse($success, '帳號建立完成!請至信箱完成確認信開通!');
            }
        }catch (\Exception $e) {
            return $this->sendError('註冊失敗! 請洽系統管理員!.', ['error'=>'註冊失敗! 請洽系統管理員!']);
        }  
    }

    /**
     * Login api
     *
     * @return \Illuminate\Http\Response
     */
    public function login(Request $request): JsonResponse
    {
        if(Auth::attempt(['email' => $request->email, 'password' => $request->password])){
            $user = Auth()->user();
            $success['token'] =  $user->createToken('MyApp')->plainTextToken;
            $success['name'] =  $user->name;
            Auth()->login($user,$remember = true);
            // dd(Auth::user());
            return $this->sendResponse($success, 'User login successfully.');
        }
        else{
            return $this->sendError('Unauthorised.', ['error'=>'Unauthorised']);
        }
    }

    public function logout(Request $request){
        try{
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            $success = ['logout' => true];
            return $this->sendResponse($success, '帳號登出完成!');
        }catch (\Exception $e) {
            return $this->sendError('帳號登出失敗! 請洽系統管理員!.', ['error'=>'帳號登出失敗! 請洽系統管理員!']);
        }
    }

    public function usercheck(Request $request): JsonResponse
    {
        if(Auth()->guest() || !Auth()->check() || is_null(Auth()->user())){
            return $this->sendResponse('notlogin.', ['error'=>'notlogin']);
        } else {
            return $this->sendResponse(Auth()->user(), 'checklogin');
        }
    }

    public function userget(Request $request): JsonResponse
    {
        if(Auth()->guest() || !Auth()->check() || is_null(Auth()->user())){
            return $this->sendResponse('notlogin.', ['error'=>'notlogin']);
        } else {
            // dd(Auth::user());
            return $this->sendResponse(Auth()->user(), 'checklogin');
        }
    }
}
