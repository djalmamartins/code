<?php

/**
 * Moves Code | Email
 *
 * Gerencia a preparação e o envio de mensagens de e-mail da aplicação.
 *
 * @author Djalma Martins
 * @package Moves\Support
 */

namespace Moves\Support;

use MovesCode\Model\Message;
use PHPMailer\PHPMailer\PHPMailer;
use InvalidArgumentException;
use Throwable;

class Email
{
    private object $data;

    private PHPMailer $mail;

    private Message $message;

    private ?string $textBody = null;

    /**
     * Inicializa a mensagem com uma instância configurável do PHPMailer.
     */
    public function __construct(?PHPMailer $mail = null)
    {
        $this->mail = $mail ?? new PHPMailer(true);
        $this->configureSmtp();
        $this->data = new \stdClass();
        $this->message = new Message();
    }

    /**
     * Define os dados principais da mensagem.
     */
    public function bootstrap(string $subject, string $body, string $recipient, string $recipientName): self
    {
        $this->data->subject = $subject;
        $this->data->body = $body;
        $this->data->recipientEmail = $recipient;
        $this->data->recipientName = $recipientName;

        return $this;
    }

    /**
     * Adiciona um anexo à mensagem.
     */
    public function attach(string $filePath, string $fileName): self
    {
        $this->data->attachments[$filePath] = $fileName;

        return $this;
    }

    /**
     * Define a alternativa em texto simples da mensagem HTML.
     */
    public function textBody(?string $textBody): self
    {
        $this->textBody = $textBody;

        return $this;
    }

    /**
     * Envia a mensagem utilizando a configuração aplicada ao PHPMailer.
     */
    public function send(string $from, string $fromName): bool
    {
        if (!isset($this->data->subject, $this->data->body, $this->data->recipientEmail)) {
            $this->message->error("Erro ao enviar, favor verifique os dados");
            return false;
        }

        $attachments = [];
        foreach ($this->data->attachments ?? [] as $path => $name) {
            $attachments[] = ['path' => $path, 'name' => $name];
        }

        return $this->sendEmail(
            $this->data->recipientEmail,
            $this->data->subject,
            $this->data->body,
            $from,
            $this->textBody,
            $fromName,
            $attachments,
            $this->data->recipientName ?? ''
        )['success'];
    }

    /**
     * Prepara e envia e-mail HTML com alternativa em texto e anexos opcionais.
     *
     * Cada anexo deve ser um caminho ou um array com `path` e `name` opcionais.
     * Erros internos do transporte nunca são devolvidos ao chamador.
     *
     * @param list<string|array{path: string, name?: string}> $attachments
     * @return array{success: bool, message: string}
     */
    public function sendEmail(
        string $recipient,
        string $subject,
        string $htmlBody,
        string $from = '',
        ?string $textBody = null,
        ?string $fromName = null,
        array $attachments = [],
        string $recipientName = ''
    ): array {
        $from = $from !== '' ? $from : (string) (getenv('MAIL_FROM_ADDRESS') ?: '');
        $fromName ??= (string) (getenv('MAIL_FROM_NAME') ?: (defined('APP_NAME') ? APP_NAME : 'Moves'));
        if (!PHPMailer::validateAddress($recipient)
            || !PHPMailer::validateAddress($from)
            || trim($subject) === ''
            || trim($htmlBody) === ''
            || $this->hasHeaderBreak($subject)
            || $this->hasHeaderBreak($fromName)
            || $this->hasHeaderBreak($recipientName)
        ) {
            return $this->failure('Dados de e-mail inválidos.');
        }

        try {
            // PHPMailer objects can be reused; clear recipient-specific state to avoid cross-send leakage.
            $this->mail->clearAllRecipients();
            $this->mail->clearAttachments();
            $this->mail->CharSet = PHPMailer::CHARSET_UTF8;
            $this->mail->SMTPDebug = 0;
            $this->mail->Subject = $subject;
            $this->mail->msgHTML($htmlBody);
            if ($textBody !== null) {
                $this->mail->AltBody = $textBody;
            }
            $this->mail->setFrom($from, $fromName);
            $this->mail->addAddress($recipient, $recipientName);

            foreach ($attachments as $attachment) {
                if (is_string($attachment)) {
                    $path = $attachment;
                    $name = basename($path);
                } elseif (is_array($attachment)
                    && is_string($attachment['path'] ?? null)
                    && (!isset($attachment['name']) || is_string($attachment['name']))
                ) {
                    $path = $attachment['path'];
                    $name = $attachment['name'] ?? basename($path);
                } else {
                    throw new InvalidArgumentException('Invalid attachment.');
                }

                if ($this->hasHeaderBreak($name) || !is_file($path) || !is_readable($path)) {
                    throw new InvalidArgumentException('Invalid attachment.');
                }

                $this->mail->addAttachment($path, $name);
            }

            if (!$this->mail->send()) {
                return $this->failure('Não foi possível enviar o e-mail.');
            }

            $this->message->success('E-mail enviado com sucesso.');

            return ['success' => true, 'message' => 'E-mail enviado com sucesso.'];
        } catch (Throwable) {
            return $this->failure('Não foi possível enviar o e-mail.');
        }
    }

    /**
     * Detecta quebras de linha que poderiam injetar cabeçalhos.
     */
    private function hasHeaderBreak(string $value): bool
    {
        return preg_match('/[\r\n]/', $value) === 1;
    }

    /**
     * Cria uma falha genérica sem expor detalhes do PHPMailer.
     *
     * @return array{success: false, message: string}
     */
    private function failure(string $message): array
    {
        $this->message->error($message);

        return ['success' => false, 'message' => $message];
    }

    /**
     * Retorna a instância do PHPMailer para configuração do transporte.
     */
    public function mail(): PHPMailer
    {
        return $this->mail;
    }

    /**
     * Retorna a mensagem gerada pela última validação ou tentativa de envio.
     */
    public function message(): Message
    {
        return $this->message;
    }

    /** Configura SMTP somente quando MAIL_HOST estiver definido no ambiente. */
    private function configureSmtp(): void
    {
        $host = getenv('MAIL_HOST');
        if ($host === false || trim($host) === '') {
            return;
        }

        $port = filter_var(getenv('MAIL_PORT') ?: '587', FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 65535],
        ]);
        $encryption = strtolower((string) (getenv('MAIL_ENCRYPTION') ?: 'tls'));
        if ($port === false || !in_array($encryption, ['tls', 'ssl', 'none'], true)
            || preg_match('/[\x00-\x20\x7f]/', $host)
        ) {
            throw new InvalidArgumentException('Invalid SMTP configuration.');
        }

        $username = (string) (getenv('MAIL_USERNAME') ?: '');
        $password = (string) (getenv('MAIL_PASSWORD') ?: '');
        if (($username === '') !== ($password === '') || ($username !== '' && $encryption === 'none')) {
            throw new InvalidArgumentException('Invalid SMTP configuration.');
        }

        $this->mail->isSMTP();
        $this->mail->Host = trim($host);
        $this->mail->Port = $port;
        $this->mail->SMTPAuth = $username !== '';
        $this->mail->Username = $username;
        $this->mail->Password = $password;
        $this->mail->Timeout = 10;
        $this->mail->SMTPSecure = match ($encryption) {
            'tls' => PHPMailer::ENCRYPTION_STARTTLS,
            'ssl' => PHPMailer::ENCRYPTION_SMTPS,
            default => '',
        };
    }
}
