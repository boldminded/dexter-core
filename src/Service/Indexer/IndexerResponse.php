<?php

namespace Litzinger\DexterCore\Service\Indexer;

class IndexerResponse
{
    private int $saved = 0;

    private int $deleted = 0;

    private array $errors = [];

    private string $message = '';

    private bool $success = true;

    public function getSaved(): int
    {
        return $this->saved;
    }

    public function setSaved(int $saved): IndexerResponse
    {
        $this->saved = $saved;
        return $this;
    }

    public function getDeleted(): int
    {
        return $this->deleted;
    }

    public function setDeleted(int $deleted): IndexerResponse
    {
        $this->deleted = $deleted;
        return $this;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function setMessage(string $message): IndexerResponse
    {
        $this->message = $message;
        return $this;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function addError(string $error): IndexerResponse
    {
        $this->errors[] = $error;
        return $this;
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function setSuccess(bool $success): IndexerResponse
    {
        $this->success = $success;
        return $this;
    }
}
