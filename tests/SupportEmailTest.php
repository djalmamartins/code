<?php

/**
 * Moves Code | Support Email Tests
 *
 * Exercita envio e cobrança com transporte simulado, sem entregar mensagens.
 *
 * @author Djalma Martins
 * @package Moves\Tests
 */

use Moves\Support\Charge;
use Moves\Support\Email;
use PHPMailer\PHPMailer\PHPMailer;

require dirname(__DIR__) . '/index.php';

class SimulatedMailer extends PHPMailer
{
    public int $simulatedSends = 0;

    public function send(): bool
    {
        $this->simulatedSends++;

        return $this->preSend();
    }
}

class FailedSimulatedMailer extends PHPMailer
{
    public function send(): bool
    {
        $this->ErrorInfo = 'Private SMTP transport details';

        return false;
    }
}

function assertSupportEmailTest(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

putenv('MAIL_HOST=smtp.example.test');
putenv('MAIL_PORT=465');
putenv('MAIL_USERNAME=test-user');
putenv('MAIL_PASSWORD=test-password');
putenv('MAIL_ENCRYPTION=ssl');
$smtpMailer = new PHPMailer(true);
new Email($smtpMailer);
assertSupportEmailTest($smtpMailer->Mailer === 'smtp', 'SMTP transport was not configured from the environment.');
assertSupportEmailTest($smtpMailer->Host === 'smtp.example.test', 'SMTP host was not applied.');
assertSupportEmailTest($smtpMailer->Port === 465, 'SMTP port was not applied.');
assertSupportEmailTest($smtpMailer->Username === 'test-user', 'SMTP username was not applied.');
assertSupportEmailTest($smtpMailer->Password === 'test-password', 'SMTP password was not applied.');
assertSupportEmailTest($smtpMailer->SMTPSecure === PHPMailer::ENCRYPTION_SMTPS, 'SMTP encryption was not applied.');
foreach (['MAIL_HOST', 'MAIL_PORT', 'MAIL_USERNAME', 'MAIL_PASSWORD', 'MAIL_ENCRYPTION'] as $variable) {
    putenv($variable);
}
putenv('MAIL_FROM_ADDRESS=remetente-configurado@example.test');
putenv('MAIL_FROM_NAME=Moves Configurado');
$configuredFromMailer = new SimulatedMailer(true);
$configuredFromEmail = new Email($configuredFromMailer);
$configuredFromResult = $configuredFromEmail->sendEmail(
    'destinatario@example.test',
    'Remetente configurado',
    '<p>Teste de configuração</p>'
);
assertSupportEmailTest($configuredFromResult['success'], 'Configured sender was not used.');
assertSupportEmailTest($configuredFromMailer->From === 'remetente-configurado@example.test', 'Configured sender address was ignored.');
assertSupportEmailTest($configuredFromMailer->FromName === 'Moves Configurado', 'Configured sender name was ignored.');
putenv('MAIL_FROM_ADDRESS');
putenv("MAIL_FROM_NAME=Moves\r\nBcc: intruso@example.test");
$headerInjectionMailer = new SimulatedMailer(true);
$headerInjectionEmail = new Email($headerInjectionMailer);
$headerInjectionResult = $headerInjectionEmail->sendEmail(
    'destinatario@example.test',
    'Cabeçalho inválido',
    '<p>Teste</p>'
);
assertSupportEmailTest(!$headerInjectionResult['success'], 'Configured sender name allowed header injection.');
assertSupportEmailTest($headerInjectionMailer->simulatedSends === 0, 'Injected sender name reached the transport.');
putenv('MAIL_FROM_NAME');

$mailer = new SimulatedMailer(true);
$email = new Email($mailer);
$attachmentPath = tempnam(sys_get_temp_dir(), 'moves-email-test-');
if ($attachmentPath === false || file_put_contents($attachmentPath, 'simulated attachment') === false) {
    throw new RuntimeException('Could not create temporary test attachment.');
}

try {
    $result = $email->sendEmail(
        'destinatario@example.test',
        'Assunto de teste',
        '<p>Conteúdo de teste</p>',
        'remetente@example.test',
        'Conteúdo alternativo',
        'Moves Teste',
        [['path' => $attachmentPath, 'name' => 'anexo.txt']],
        'Pessoa Teste'
    );

    assertSupportEmailTest($result['success'], 'Simulated email did not succeed.');
    assertSupportEmailTest($mailer->AltBody === 'Conteúdo alternativo', 'Plain-text alternative was not set.');
    assertSupportEmailTest(count($mailer->getAttachments()) === 1, 'Optional attachment was not added.');
    assertSupportEmailTest($mailer->FromName === 'Moves Teste', 'Configurable sender name was ignored.');

    $second = $email->sendEmail(
        'outra-pessoa@example.test',
        'Segundo assunto',
        '<p>Segunda mensagem</p>',
        'remetente@example.test'
    );
    assertSupportEmailTest($second['success'], 'Second simulated email did not succeed.');
    assertSupportEmailTest(count($mailer->getToAddresses()) === 1, 'Recipient state leaked between sends.');
    assertSupportEmailTest(
        $mailer->getToAddresses()[0][0] === 'outra-pessoa@example.test',
        'Wrong recipient after reuse.'
    );
    assertSupportEmailTest(count($mailer->getAttachments()) === 0, 'Attachment state leaked between sends.');

    $legacyMailer = new SimulatedMailer(true);
    $legacyEmail = (new Email($legacyMailer))
        ->bootstrap('API anterior', '<p>Compatibilidade</p>', 'legado@example.test', 'Legado')
        ->textBody('Alternativa simples');
    assertSupportEmailTest($legacyEmail->send('remetente@example.test', 'Moves Teste'), 'Legacy send() API regressed.');
    assertSupportEmailTest($legacyMailer->AltBody === 'Alternativa simples', 'Legacy API lost the text alternative.');

    $beforeRejected = $mailer->simulatedSends;
    $rejected = $email->sendEmail(
        'destinatario@example.test',
        "Assunto\r\nBcc: intruso@example.test",
        '<p>Teste</p>',
        'remetente@example.test'
    );
    assertSupportEmailTest(!$rejected['success'], 'Header injection was accepted.');
    assertSupportEmailTest($mailer->simulatedSends === $beforeRejected, 'Invalid email was passed to the transport.');

    $failedSend = (new Email(new FailedSimulatedMailer(true)))->sendEmail(
        'destinatario@example.test',
        'Falha simulada',
        '<p>Teste</p>',
        'remetente@example.test'
    );
    assertSupportEmailTest(!$failedSend['success'], 'Simulated transport failure was reported as success.');
    assertSupportEmailTest(
        !str_contains($failedSend['message'], 'Private SMTP transport details'),
        'SMTP internals were exposed to the caller.'
    );

    $chargeMailer = new SimulatedMailer(true);
    $charge = new Charge(new Email($chargeMailer), 'financeiro@example.test', 'Moves Financeiro');
    $chargeResult = $charge->sendCharge(
        'cliente@example.test',
        '<Cliente>',
        1299.9,
        '<script>alert(1)</script>',
        '2026-12-15',
        'https://pagamentos.example.test/cobranca/123'
    );
    assertSupportEmailTest($chargeResult['success'], 'Simulated charge email did not succeed.');
    assertSupportEmailTest(!str_contains($chargeMailer->Body, '<script>'), 'Charge description was not HTML escaped.');
    assertSupportEmailTest(str_contains($chargeMailer->AltBody, 'R$ 1.299,90'), 'Charge amount was not included.');
    assertSupportEmailTest(str_contains($chargeMailer->Body, '15/12/2026'), 'Charge due date was not included.');

    $beforeUnsafeUrl = $chargeMailer->simulatedSends;
    $unsafeUrlResult = $charge->sendCharge(
        'cliente@example.test',
        'Cliente',
        10,
        'Teste',
        '2026-12-15',
        'javascript:alert(1)'
    );
    assertSupportEmailTest(!$unsafeUrlResult['success'], 'Unsafe payment URL was accepted.');
    assertSupportEmailTest($chargeMailer->simulatedSends === $beforeUnsafeUrl, 'Unsafe payment URL reached transport.');

    $credentialUrlResult = $charge->sendCharge(
        'cliente@example.test',
        'Cliente',
        10,
        'Teste',
        '2026-12-15',
        'https://usuario:senha@pagamentos.example.test/cobranca'
    );
    assertSupportEmailTest(!$credentialUrlResult['success'], 'Credential-bearing payment URL was accepted.');
    assertSupportEmailTest($chargeMailer->simulatedSends === $beforeUnsafeUrl, 'Credential URL reached transport.');
} finally {
    unlink($attachmentPath);
}

echo "Support email tests passed (simulated transport; no messages delivered).\n";
