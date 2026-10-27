<!doctype html>
<html lang="fr">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <title>{{ $mail_subject }}</title>
</head>
<body style="margin:0;padding:0;background:#eef1f6;font-family:Arial,Helvetica,sans-serif;">
    {!! clean($mail_template) !!}

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;background:#eef1f6;">
        <tr>
            <td align="center" style="padding:0 20px 24px;">
                <table role="presentation" width="640" cellspacing="0" cellpadding="0" border="0" style="width:100%;max-width:640px;border-collapse:collapse;">
                    <tr>
                        <td align="center" style="padding:16px 24px;background:#15171b;color:#aab2c0;font-size:12px;line-height:19px;">
                            Vous ne souhaitez plus recevoir ces nouvelles?
                            <a href="{{ $unsubscribe_url }}" style="color:#ffffff;text-decoration:underline;">Se désabonner</a>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
