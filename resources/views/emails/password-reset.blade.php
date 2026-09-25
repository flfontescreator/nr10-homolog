<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Redefinição de senha — GreenJob</title>
</head>
<body style="margin:0;padding:0;background:#f5f7fa;font-family:'Segoe UI',system-ui,-apple-system,Arial,sans-serif;color:#1f2937;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f5f7fa;padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border:1px solid #e2e8f0;border-radius:10px;overflow:hidden;">
                    <!-- Cabeçalho -->
                    <tr>
                        <td style="background:#2563eb;padding:22px 32px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td>
                                        <span style="font-size:18px;font-weight:700;color:#ffffff;">Gestão de Conformidades</span>
                                        <span style="font-size:15px;font-weight:400;color:#dbeafe;"> NR-10</span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <!-- Conteúdo -->
                    <tr>
                        <td style="padding:32px 32px 8px 32px;">
                            <h1 style="margin:0 0 6px 0;font-size:22px;color:#1f2937;">Redefinição de senha</h1>
                            <p style="margin:0;color:#64748b;font-size:13px;">Você solicitou uma nova senha para sua conta.</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:8px 32px;">
                            <p style="margin:12px 0;">Olá, <strong>{{ $name }}</strong>!</p>
                            <p style="margin:12px 0;line-height:1.6;">Recebemos uma solicitação para redefinir a senha da sua conta no <strong>GreenJob — Gestão de Conformidades NR-10</strong>. Para continuar, clique no botão abaixo:</p>
                            <table role="presentation" cellpadding="0" cellspacing="0" style="margin:24px 0;">
                                <tr>
                                    <td align="center" style="border-radius:8px;">
                                        <a href="{{ $url }}"
                                           style="display:inline-block;padding:12px 28px;background:#2563eb;color:#ffffff;text-decoration:none;font-weight:600;font-size:14px;border-radius:8px;">Redefinir minha senha</a>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin:12px 0;line-height:1.6;">Se o botão não funcionar, copie e cole este link no navegador:</p>
                            <p style="margin:0;padding:10px 14px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;font-size:12px;color:#64748b;word-break:break-all;">{{ $url }}</p>
                            <p style="margin:20px 0 0 0;font-size:13px;color:#64748b;">Este link expira em <strong>{{ $expiresInMinutes }} minutos</strong>.</p>
                            <p style="margin:12px 0 0 0;font-size:13px;color:#64748b;">Se você não fez essa solicitação, ignore este e-mail — sua senha atual continua válida.</p>
                        </td>
                    </tr>
                    <!-- Rodapé -->
                    <tr>
                        <td style="padding:20px 32px;border-top:1px solid #e2e8f0;background:#f8fafc;">
                            <p style="margin:0;font-size:12px;color:#94a3b8;">GreenJob — Gestão de Conformidades NR-10</p>
                            <p style="margin:4px 0 0 0;font-size:12px;color:#64748b;">Segurança elétrica e conformidade regulatória.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>