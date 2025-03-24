<?php

namespace Dustin\ShopwareUtils\Core\Content\MailTemplate\Installer;

use Dustin\ShopwareUtils\Core\Framework\AdditionalBundle;
use Dustin\ShopwareUtils\Core\Framework\Plugin;
use Dustin\ShopwareUtils\Core\Framework\Validation\FileExists;
use Dustin\ShopwareUtils\Core\Framework\Validation\FileNotEmpty;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class MailTemplateTranslationValidator
{
    public function __construct(
        private readonly AdditionalBundle|Plugin $bundle,
        private readonly string $identifier
    ) {}

    public function validate(mixed $data, ExecutionContextInterface $context): void
    {
        $locale = $this->getLocaleFromPath($context->getPropertyPath());

        $plainFile = MailTemplateUtil::getContentFile($this->identifier, $locale, false, $this->bundle);
        $htmlFile = MailTemplateUtil::getContentFile($this->identifier, $locale, true, $this->bundle);

        $this->validateConstraints(
            [new FileExists($plainFile), new FileNotEmpty($plainFile, ['trimContent' => true])],
            '[contentPlain]',
            $context
        );

        $this->validateConstraints(
            [new FileExists($htmlFile), new FileNotEmpty($htmlFile, ['trimContent' => true])],
            '[contentHtml]',
            $context
        );
    }

    protected function validateConstraints(array $constraints, string $subPath, ExecutionContextInterface $context): void
    {
        $violations = $context->getValidator()
            ->startContext()
            ->atPath($context->getPropertyPath($subPath))
            ->validate(null, $constraints)
            ->getViolations();

        foreach($violations as $violation) {
            $context->getViolations()->add($violation);
        }
    }

    protected function getLocaleFromPath(string $path): string
    {
        return \substr($path, \strlen($path) - 6, 5);
    }

}