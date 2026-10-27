<?php

namespace App\Agents;

use App\Agents\Team\Accountant;
use App\Agents\Team\Analyst;
use App\Agents\Team\ContentCreator;
use App\Agents\Team\PhotoDesigner;
use App\Agents\Team\ReelMaker;
use App\Agents\Team\TableKeeper;
use App\Agents\Team\VideoEditor;

/**
 * Every AI agent of the platform, in the order the business sees them. A
 * new agent is a class extending Agent added here, plus its price on the
 * platform level; nothing in the database changes.
 */
final class AgentCatalog
{
    /** module_prices.feature_key and plans.included_modules of an agent: "agent:accountant". */
    public const PRICE_PREFIX = 'agent:';

    /** @var list<class-string<Agent>> */
    private const AGENTS = [
        Analyst::class,
        Accountant::class,
        TableKeeper::class,
        ContentCreator::class,
        ReelMaker::class,
        VideoEditor::class,
        PhotoDesigner::class,
    ];

    /** @var array<string, Agent>|null */
    private static ?array $agents = null;

    /** @return array<string, Agent> by key */
    public static function all(): array
    {
        if (self::$agents === null) {
            self::$agents = [];
            foreach (self::AGENTS as $class) {
                $agent = new $class;
                self::$agents[$agent->key()] = $agent;
            }
        }

        return self::$agents;
    }

    public static function find(?string $key): ?Agent
    {
        return $key === null ? null : (self::all()[$key] ?? null);
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::all());
    }

    /** @return list<string> agent:analyst, agent:accountant... */
    public static function priceKeys(): array
    {
        return array_map(fn (Agent $agent) => $agent->priceKey(), array_values(self::all()));
    }

    /** The agent a price key belongs to ("agent:analyst" → Analyst). */
    public static function fromPriceKey(string $key): ?Agent
    {
        return str_starts_with($key, self::PRICE_PREFIX) ? self::find(substr($key, strlen(self::PRICE_PREFIX))) : null;
    }
}
