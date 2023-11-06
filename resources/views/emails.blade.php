<!doctype html>
<html>
    <head>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title>Quantum Capital Global</title>
    </head>
    <body>
        <p>Hi {{ $emailData['first_name'] }},</p>
        <br/>
        <p>Your withdrawal request, <span>{{ $emailData['payment_id'] }}</span> has been rejected.</p>
        <p>The withdrawal amount of ${{ $emailData['payment_amount'] }} has been returned to your cash wallet.</p>

        <br/>
        <br/>

        <p>你的提款請求，<span>{{ $emailData['payment_id'] }}</span> 被拒绝了。</p>
        <p>提款金額的 ${{ $emailData['payment_amount'] }} 已退回至你的現金錢包</p>
    </body>
</html>