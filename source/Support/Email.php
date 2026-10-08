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
use Throwable;

class Email
{
    private object $data;

    private PHPMailer $mail;

    private Message $message;

    /**
     * Inicializa a mensagem com uma instância configurável do PHPMailer.
     */
    public function __construct(?PHPMailer $mail = null)
    {
        $this->mail = $mail ?? new PHPMailer(true);
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
     * Envia a mensagem utilizando a configuração aplicada ao PHPMailer.
     */
    public function send(string $from, string $fromName): bool
    {
        if (!isset($this->data->subject, $this->data->body, $this->data->recipientEmail)) {
            $this->message->error("Erro ao enviar, favor verifique os dados");
            return false;
        }

        if (!PHPMailer::validateAddress($this->data->recipientEmail)) {
            $this->message->warning("O e-mail de destinatário não é válido");
            return false;
        }

        if (!PHPMailer::validateAddress($from)) {
            $this->message->warning("O e-mail de remetente não é válido");
            return false;
        }

        try {
            $this->mail->Subject = $this->data->subject;
            $this->mail->msgHTML($this->data->body);
            $this->mail->addAddress($this->data->recipientEmail, $this->data->recipientName);
            $this->mail->setFrom($from, $fromName);

            foreach ($this->data->attachments ?? [] as $path => $name) {
                $this->mail->addAttachment($path, $name);
            }

            return $this->mail->send();
        } catch (Throwable) {
            $this->message->error("Não foi possível enviar o e-mail");
            return false;
        }
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
}
