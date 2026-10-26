<?php

namespace App\Agents;

/**
 * One AI agent: a member of a business's virtual staff with a role (an
 * analyst, an accountant, a content creator...), a name, what it can do,
 * which of the assistant's look-ups it may use and how it works. Agents are
 * defined in code, one class each, listed in AgentCatalog: adding one needs
 * no migration, only its price (Platform → plans and prices).
 *
 * Texts shown to the owner are Armenian first, with Russian and English
 * names; the instructions to the model are in English, and the agent
 * answers in the language the owner writes in.
 */
abstract class Agent
{
    /** Stable id: tenant_agents.agent_key, conversations, prices. */
    abstract public function key(): string;

    /** @return array{hy: string, ru: string, en: string} the agent's first name */
    abstract public function persona(): array;

    /** @return array{hy: string, ru: string, en: string} its job title */
    abstract public function role(): array;

    /** One line under the name. */
    abstract public function tagline(): string;

    abstract public function description(): string;

    /** @return list<string> what it does, a few short lines */
    abstract public function skills(): array;

    /**
     * The look-ups of the assistant it may use (App\Assistant\BusinessTools
     * and the social module's; those of a module the business doesn't have
     * are left out anyway). Every agent also keeps the brand profile and its
     * own notes (AgentTools).
     *
     * @return list<string>
     */
    abstract public function tools(): array;

    /** How it works and what it gives back, for the model (English). */
    abstract public function instructions(): string;

    /** Its first words in a new chat. */
    abstract public function greeting(): string;

    /** @return list<string> questions to start with */
    abstract public function suggestions(): array;

    /** Monthly price (AMD) it starts with; the super admin sets the real one. */
    public function defaultPrice(): int
    {
        return 2900;
    }

    /**
     * Whether it gets the business's figures in its prompt (sales, orders,
     * finance): the analysts do; the creative agents get a short summary and
     * look up what they need, which keeps their questions cheap.
     */
    public function usesFigures(): bool
    {
        return false;
    }

    /** Whether it can't do good work without the brand profile (it asks for it first). */
    public function needsBrand(): bool
    {
        return true;
    }

    /**
     * Whether it makes finished pictures itself (posters, posts, covers:
     * App\Media\DesignStudio), when the media service is connected.
     */
    public function designs(): bool
    {
        return false;
    }

    /**
     * Whether it cuts the owner's clips into videos itself
     * (App\Media\VideoStudio), when the media service is connected.
     */
    public function edits(): bool
    {
        return false;
    }

    /** What it can't do yet, said plainly on its card (null: nothing to say). */
    public function limitation(): ?string
    {
        return null;
    }

    /** The key of its price and of a plan that includes it (module_prices.feature_key). */
    public function priceKey(): string
    {
        return AgentCatalog::PRICE_PREFIX.$this->key();
    }

    /** «Անի · Հաշվապահ» */
    public function label(string $locale = 'hy'): string
    {
        return ($this->persona()[$locale] ?? $this->persona()['hy']).' · '.($this->role()[$locale] ?? $this->role()['hy']);
    }

    /**
     * What the owner sees of it, with no business state.
     *
     * @return array<string, mixed>
     */
    public function present(): array
    {
        return [
            'key' => $this->key(),
            'persona' => $this->persona(),
            'role' => $this->role(),
            'label' => $this->label(),
            'tagline' => $this->tagline(),
            'description' => $this->description(),
            'skills' => $this->skills(),
            'limitation' => $this->limitation(),
            'greeting' => $this->greeting(),
            'suggestions' => $this->suggestions(),
            'needsBrand' => $this->needsBrand(),
        ];
    }
}
