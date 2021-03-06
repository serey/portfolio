<?php
namespace Application\Bundle\DefaultBundle\Service;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Class MailerService
 */
class MailerService
{
    /**
     * @var \Twig_Environment
     */
    private $twig;

    /**
     * @var \Swift_Mailer
     */
    private $mailer;

    /**
     * @var array
     */
    private $options = [];

    /**
     * @param \Swift_Mailer     $mailer
     * @param \Twig_Environment $twig
     * @param array             $options
     * 
     * @throws \InvalidArgumentException
     */
    public function __construct(\Swift_Mailer $mailer, \Twig_Environment $twig, array $options)
    {
        $this->mailer = $mailer;
        $this->twig = $twig;
        if (empty($options)) {
            throw new \InvalidArgumentException('Options array can not be empty');
        }
        $this->options = $options;
    }

    /**
     * @param string|array $to
     * @param string       $subject
     * @param string       $template
     * @param array        $templateParams
     * @param array        $attachments
     *
     * @return bool|int
     */
    public function send($to, $subject, $template, array $templateParams = [], array $attachments = [])
    {
        $body = $this->getBody($template, $templateParams);

        return $this->sendMessage($subject, $body, $to, $attachments);
    }

    /**
     * @param string $template
     * @param array  $templateParams
     *
     * @return string
     */
    private function getBody($template, array $templateParams = [])
    {
        $templateContent = $this->twig->loadTemplate(
            $template
        );

        return $templateContent->render($templateParams);
    }

    /**
     * @param string       $subject
     * @param string       $body
     * @param string|array $to
     * @param array        $attachments
     *
     * @return bool|int
     */
    private function sendMessage($subject, $body, $to, array $attachments = [])
    {
        if (is_array($to)) {
            list($toEmail, $toName) = $to;
        } else {
            $toEmail = $to;
            $toName = null;
        }
        try {
            /** @var \Swift_Message $message */
            $message = \Swift_Message::newInstance()
                ->setSubject($subject)
                ->setFrom($this->options['fromEmail'], $this->options['fromName'])
                ->setTo($toEmail, $toName)
                ->setBody($body, 'text/html');

            if (!empty($attachments)) {
                /** @var UploadedFile $file */
                foreach ($attachments as $file) {
                    $message->attach(\Swift_Attachment::fromPath($file->getRealPath())->setFilename($file->getFilename()));
                }
            }

            return $this->mailer->send($message);
        } catch (\Exception $e) {
            return false;
        }
    }
} 