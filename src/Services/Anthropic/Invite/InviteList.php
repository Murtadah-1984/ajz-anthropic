<?php

declare(strict_types=1);


namespace Ajz\Anthropic\Services\Anthropic\Invite;


final class InviteList
{
    /** @var Invite[] */
    public array $data;
    public bool $has_more;
    public ?string $first_id;
    public ?string $last_id;

    public function __construct(array $data)
    {
        $this->data = array_map(fn($invite) => new Invite($invite), $data['data']);
        $this->has_more = $data['has_more'];
        $this->first_id = $data['first_id'];
        $this->last_id = $data['last_id'];
    }
}
