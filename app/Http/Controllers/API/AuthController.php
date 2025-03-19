<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Http\Controllers\API\BaseController as BaseController;
use App\Http\Controllers\SendEmailController;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Validator;
use DateTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Crypt;

class AuthController extends BaseController
{
    /** 產生亂數密碼($numbers: 數量) */
    public function password_generate($numbers)
    {
        $data = '1234567890ABCDEFGHIJKLMNOPQRSTUVWXYZabcefghijklmnopqrstuvwxyz';
        return substr(str_shuffle($data),0,$numbers);
    }
    
    /** (一般註冊)帳號重複檢查(不存在將return null) */
    public function accountduplicate($checkemail){
        $check = User::where('email', '=', trim($checkemail))->where('group_type','=','Normal')->first();
        return $check;
    }

    /** 密碼錯誤累計，達5次即進行帳號鎖定(return true) */
    public function DoPwdInputError($useremail){
        $user = USER::where('email', $useremail)->first();
        $err_count = intval($user->pw_err_count) + 1;
        $form_data['pw_err_count'] = $err_count;
        $form_data['pw_err_date'] = now();
        $user->update($form_data);
        if($err_count >= 5) {
            $form_data['is_lock'] = true;
            $user->update($form_data);
            return true;
        }
        else return false;
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
     * 驗證cloudflare turnstile captcha
     */
    public function verifyTurnstile(Request $request){
        $validator = Validator::make($request->all(), [
            'token' => 'required|string'
        ]);
        if($validator->fails()){
            return $this->sendError('Validation Error.', $validator->errors());
        }
        $secret = config('services.turnstile.secret_key');
        $response = Http::post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
            'secret' => $secret,
            'response' => $request->input('token'),
        ]);

        $responseData = $response->json();

        // 根據驗證結果返回
        if ($responseData['success']) {
            return response()->json(['success' => true]);
        } else {
            return response()->json(['success' => false, 'message' => 'Verification failed']);
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
            'area_type' => 'required',
            'turnstileToken' => 'required|string',
            'TokenisVerified' => 'required|boolean|in:true'
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
                $input['password'] = Hash::make($input['password']);
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
            // dd($e);
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
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if($validator->fails()){
            return $this->sendError('Validation Error.', $validator->errors());
        }

        try{
            $input = $request->all();
            // $user = User::where('email', '=', $input['email'])->where('password','=',$input['password'])->get();
            $user = User::where('email', '=', $input['email'])->where('user_status','=',true)->first();
            if ($user) {
                if($user->is_lock) return $this->sendError('登入失敗! 帳號已被鎖定! 請洽系統管理員.', ['error'=>'登入失敗! 帳號已被鎖定! 請洽系統管理員.']);
                if(Hash::check($input['password'],$user->password)){
                    $form_data['pw_err_count'] = 0;
                    $form_data['pw_err_date'] = null;
                    $user->update($form_data);
                    Auth()->login($user);
                    $user = Auth()->user();
                    $success['token'] =  $user->createToken('MyApp')->plainTextToken;
                    $success['name'] =  $user->name;
                    return $this->sendResponse($success, '使用者登入成功.');
                }else{
                    // 密碼輸入錯誤累計，return是否鎖定
                    if(!$this->DoPwdInputError($user->email)){
                        return $this->sendError('登入失敗! 請再次確認帳號密碼輸入是否正確!.', ['error'=>'登入失敗! 請再次確認帳號密碼輸入是否正確!.']);
                    }else return $this->sendError('登入失敗! 密碼輸入錯誤達5次，帳號鎖定，請洽系統管理員!.', ['error'=>'登入失敗! 密碼輸入錯誤達5次，帳號鎖定，請洽系統管理員!.']);                 
                }
            }else{
                return $this->sendError('登入失敗! 請再次確認帳號是否註冊/驗證完成!.', ['error'=>'登入失敗! 請再次確認帳號是否註冊/驗證完成!.']);
            }
            // if(!!$user && $user->user_status){
            // if(Auth::attempt(['email' => $input['email'], 'password' => $input['password'], 'user_status' => true])){
            //     $user = Auth()->user();
            //     $success['token'] =  $user->createToken('MyApp')->plainTextToken;
            //     $success['name'] =  $user->name;
            //     Auth()->login($user,$remember = true);
            //     // dd(Auth::user());
            //     return $this->sendResponse($success, 'User login successfully.');
            // }else return $this->sendError('登入失敗! 請再次確認帳號是否完成驗證/輸入之帳號密碼正確!.', ['error'=>'登入失敗! 請再次確認帳號是否完成驗證/輸入之帳號密碼正確!.']);
            // if(Auth::attempt(['email' => $input['email'], 'password' => $input['password'], 'user_status' => true])){
            //     $user = Auth()->user();
            //     $success['token'] =  $user->createToken('MyApp')->plainTextToken;
            //     $success['name'] =  $user->name;
            //     Auth()->login($user,$remember = true);
            //     // dd(Auth::user());
            //     return $this->sendResponse($success, 'User login successfully.');
            // }else $this->sendError('登入失敗! 請再次確認帳號是否完成驗證/輸入之帳號密碼正確!.', ['error'=>'登入失敗! 請再次確認帳號是否完成驗證/輸入之帳號密碼正確!.']);
        }catch (\Exception $e) {
            // dd($e);
            return $this->sendError('登入失敗! 請洽系統管理員!.', ['error'=>'登入失敗! 請洽系統管理員!']);
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

    /**
     * 使用者忘記密碼，寄送驗證碼給使用者
     */
    public function SendVcode(Request $request)
    {
        $success = [];
        $validator = Validator::make($request->all(), [
            'email' => 'required|email'
        ]);

        if($validator->fails()){
            return $this->sendError('Validation Error.', $validator->errors());
        }

        try{
            $input = $request->all();
            $user = User::where('email', $input['email'])->first();

            if (!isset($user)) {
                return $this->sendError('此Email帳號' . $input['email'] . '不存在');
            }
            // 先刪除暨有資料
            DB::table(('password_reset_tokens'))
                ->where('email', $input['email'])
                ->delete();
            // 送出重置密碼信件到用戶信箱
            $token = Str::random(6);
            $prt = DB::table('password_reset_tokens')
                ->insert([
                    'email' => $input['email'],
                    'token' => $token,
                    'created_at' => now()
                ]);

            $SendEmailController = new SendEmailController();
            $SendEmailController->SendVcode($input['email'],$token);
            return $this->sendResponse($success, '忘記密碼之Email驗證碼已發送完成!請至信箱取得驗證碼!');
        }catch (\Exception $e) {
            return $this->sendError('忘記密碼之Email驗證碼取得失敗! 請重新操作或洽系統管理員!.', ['error'=>'忘記密碼之Email驗證碼取得失敗! 請重新操作或洽系統管理員!']);
        }
    }

    /**
     * 使用者忘記密碼，確認驗證碼正確性後，寄送新密碼給使用者
     */
    public function SendFpassword(Request $request)
    {
        $success = [];
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'vcode' => 'required'
        ]);

        if($validator->fails()){
            return $this->sendError('Validation Error.', $validator->errors());
        }

        try{
            $input = $request->all();
            
            $prt = DB::table(('password_reset_tokens'))
                ->where('email', $input['email'])
                ->where('token', $input['vcode'])
                ->first();
            if (!isset($prt)) {
                return $this->sendError('此Email帳號' . $input['email'] . '沒有重設密碼需求');
            }
            $randomstr = $this->password_generate(8);
            $form_data['password'] = Hash::make($randomstr);
            // $form_data['password'] = $randomstr;
            $form_data['resetpwd'] = '1'; //是否為忘記密碼重置
            // dd($form_data);
            $user = USER::where('email', $input['email'])->first();
            $user->update($form_data);
            
            $SendEmailController = new SendEmailController();
            $SendEmailController->SendnewPassword($input['email'],$randomstr);
            return $this->sendResponse($success, '新密碼已完成Email發送!請至信箱取得新密碼!');
        }catch (\Exception $e) {
            return $this->sendError('驗證碼確認/新密碼發送失敗! 請重新操作或洽系統管理員!.', ['error'=>'驗證碼確認/新密碼發送失敗! 請重新操作或洽系統管理員!']);
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
