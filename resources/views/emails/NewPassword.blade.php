<!DOCTYPE html>
<html>
<head>
    <title>影像油污辨識雲端分析服務</title>
</head>
<body>
    <p>您好，歡迎使用影像油污辨識雲端分析服務，系統已完成新密碼建立，資訊如下：</p>
    <div>Email帳號: {{ $mailData['email'] }}</div>
    <div>新密碼: {{ $mailData['pwd'] }}</div>

    <p>提醒您以上述資訊登入平台服務(<a href={{ request()->root() }}>{{ request()->root() }}</a>)，並於登入後進行密碼變更操作。</p>
</body>
</html>