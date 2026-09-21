<?php

namespace NoriaLabs\Send\Notifications;

class SmsMessage
{
    protected string $content = '';

    protected ?string $sender = null;

    protected ?string $template = null;

    /** @var array<string, mixed> */
    protected array $variables = [];

    /** @var array<string, string> */
    protected array $tags = [];

    protected ?string $scheduledAt = null;

    protected ?string $idempotencyKey = null;

    public static function make(string $content = ''): self
    {
        return (new self)->content($content);
    }

    public function content(string $content): self
    {
        $this->content = $content;

        return $this;
    }

    public function sender(string $sender): self
    {
        $this->sender = $sender;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $variables
     */
    public function template(string $slug, array $variables = []): self
    {
        $this->template = $slug;
        $this->variables = $variables;

        return $this;
    }

    /**
     * @param  array<string, string>  $tags
     */
    public function tags(array $tags): self
    {
        $this->tags = $tags;

        return $this;
    }

    public function scheduleAt(string $scheduledAt): self
    {
        $this->scheduledAt = $scheduledAt;

        return $this;
    }

    public function idempotencyKey(string $key): self
    {
        $this->idempotencyKey = $key;

        return $this;
    }

    public function key(): ?string
    {
        return $this->idempotencyKey;
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(string $to): array
    {
        return array_filter([
            'to' => $to,
            'from' => $this->sender,
            'text' => $this->content === '' ? null : $this->content,
            'template' => $this->template,
            'variables' => $this->variables === [] ? null : $this->variables,
            'tags' => $this->tags === [] ? null : $this->tags,
            'scheduled_at' => $this->scheduledAt,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
