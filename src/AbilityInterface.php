<?php

namespace Drupal\user_ability;

/**
 * Implemented by a site's ability enum.
 *
 * An ability names a business capability being asked about — "can this user
 * publish content?" — not a mechanism. By convention, enum cases are named
 * as verbs or verb phrases representing the action being performed (e.g.
 * `PublishArticles`, `DeleteComments`, `ViewReports`).
 *
 * Checks are keyed by fully-qualified case reference
 * (`$ability::class . '::' . $ability->name`), so a backed enum is not
 * required — \BackedEnum extends \UnitEnum, so one is still permitted for a
 * consumer who wants one for other reasons.
 *
 * \JsonSerializable is mandatory, not advisory: json_encode() cannot
 * serialize a pure enum and returns FALSE for the *entire* surrounding
 * payload, silently, with no exception raised unless the caller opts into
 * JSON_THROW_ON_ERROR. Requiring this interface method makes that failure
 * mode impossible to hit by accident.
 *
 * Example implementation:
 * @code
 * enum SiteAbility implements AbilityInterface {
 *   case PublishArticles;
 *   case DeleteComments;
 *
 *   public function jsonSerialize(): string {
 *     return $this->name;
 *   }
 * }
 * @endcode
 */
interface AbilityInterface extends \UnitEnum, \JsonSerializable {}
