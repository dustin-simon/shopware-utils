<?php

namespace Dustin\ShopwareUtils\Core\Framework\Exception;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Validator\ConstraintViolationListInterface;

class ConstraintViolationException extends HttpException
{

    public function __construct(
        private readonly ConstraintViolationListInterface $violations,
        private readonly array $inputData
    ) {
        $message = \sprintf(
            "Constraint validation failed. Caught %s errors:\n%s",
            \count($violations),
            $this->getViolationsMessage($violations)
        );

        parent::__construct(Response::HTTP_BAD_REQUEST, $message);
    }

    public function getViolations(): ConstraintViolationListInterface
    {
        return $this->violations;
    }

    public function getInputData(): array
    {
        return $this->inputData;
    }

    private function getViolationsMessage(ConstraintViolationListInterface $violations): string
    {
        $message = '';

        foreach($violations as $violation) {
            $message .= ' • '.$violation->getPropertyPath().': '.$violation->getMessage()."\n";
        }

        return $message;
    }
}