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
        <p>Your withdrawal request, <span>{{ $emailData['payment_id'] }}</span> - {{ $emailData['status'] }}</p>
        <p>The withdrawal amount of ${{ $emailData['payment_amount'] }} {{ $emailData['message-en'] }}</p>
        <p>Reason: {{ $emailData['reason'] }}</p>

        <br/>
        <br/>

        <p>你的提款請求，<span>{{ $emailData['payment_id'] }}</span> - {{ $emailData['status'] }}</p>
        <p>提款金額的 ${{ $emailData['payment_amount'] }} {{ $emailData['message-tw'] }}</p>
        <p>原因：{{ $emailData['reason'] }}</p>
    </body>
</html>