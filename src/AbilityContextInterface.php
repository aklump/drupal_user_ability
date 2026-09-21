<?php

namespace Drupal\user_ability;

/**
 * Marker for whatever a site's checks need beyond the account.
 *
 * Deliberately empty — a group, a node, a taxonomy term are all site
 * concepts, not generic ones. Each site declares its own concrete shape
 * implementing this interface, one class per distinct shape a check
 * requires. A check declares which shape it requires via
 * `AbilityCheckInterface::getContextClass()`; `AbilityChecker::check()`
 * enforces that declaration centrally, throwing `\InvalidArgumentException`
 * if the caller passes a context of the wrong class.
 *
 * Examples:
 * @code
 * // A context wrapping a specific, required entity.
 * final class NodeContext implements AbilityContextInterface {
 *   public function __construct(
 *     public readonly NodeInterface $node,
 *   ) {}
 * }
 *
 * final class PublishArticleCheck implements AbilityCheckInterface {
 *   public static function getContextClass(): string {
 *     return NodeContext::class;
 *   }
 *   // ...
 * }
 *
 * // A multi-entity or domain context.
 * final class GroupContentContext implements AbilityContextInterface {
 *   public function __construct(
 *     public readonly GroupInterface $group,
 *     public readonly NodeInterface $node,
 *   ) {}
 * }
 *
 * final class ModerateGroupContentCheck implements AbilityCheckInterface {
 *   public static function getContextClass(): string {
 *     return GroupContentContext::class;
 *   }
 *   // ...
 * }
 *
 * // A context carrying scalar or relational criteria.
 * final class DocumentContext implements AbilityContextInterface {
 *   public function __construct(
 *     public readonly int $documentId,
 *     public readonly string $targetStatus,
 *   ) {}
 * }
 *
 * final class TransitionDocumentCheck implements AbilityCheckInterface {
 *   public static function getContextClass(): string {
 *     return DocumentContext::class;
 *   }
 *   // ...
 * }
 * @endcode
 */
interface AbilityContextInterface {}
