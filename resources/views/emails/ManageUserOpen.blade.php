<!DOCTYPE html>
<html>
<head>
    <title>影像油污辨識雲端分析服務</title>
</head>
<body>
    <p>影像油污辨識雲端分析服務系統管理員您好，{{ $mailData['userObj']->name }} 使用者已完成Email驗證，以下為使用者資訊：</p>
    <div>姓名: {{ $mailData['userObj']->name }}</div>
    <div>Email帳號: {{ $mailData['userObj']->email }}</div>
    <div>所屬區域: 
        @if($mailData['userObj']->area_type === 1)
            大林
        @elseif($mailData['userObj']->area_type === 2)
            桃園
        @else
            大林、桃園
        @endif
    </div>

    <p>提醒您請登入平台服務(<a href={{ request()->root() }}>{{ request()->root() }}</a>)，進行使用者帳號開通操作。</p>
</body>
</html>