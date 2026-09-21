<!--
id: readme
tags: ''
-->

# user_ability

> Ask "can this user do X?" in Drupal through named, cache-aware ability checks instead of scattered permission logic.

![general](../../images/hero.jpg)

## Summary

Access logic in a Drupal site tends to leak everywhere: a controller checks a permission, a form checks a role, a block checks ownership, and nobody can say in one place what "can publish an article" actually means. User Ability gives each business capability a name (an enum case such as `SiteAbility::PublishArticle`), puts the rule that decides it in one dedicated check service, and routes every question through a single dispatcher, `user_ability.checker`. The answer is always a cache-aware `AccessResult`, so it is safe to use in render arrays and route access. Each check also states its rule in plain language through `getBusinessRule()`, so a non-developer can confirm the system enforces what the business agreed to.

## Quick Start

Enable the module, then add these three pieces to one of your own modules (`my_module` here).

An ability enum, one case per capability, named as a verb phrase:

```php
// my_module/src/Enum/SiteAbility.php
namespace Drupal\my_module\Enum;

use Drupal\user_ability\AbilityInterface;

enum SiteAbility implements AbilityInterface {
  case ViewReports;

  public function jsonSerialize(): string {
    return $this->name;
  }
}
```

A check that decides it. This one needs no context beyond the user, so it declares `NullContext`:

```php
// my_module/src/AbilityCheck/ViewReportsCheck.php
namespace Drupal\my_module\AbilityCheck;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\user\UserInterface;
use Drupal\user_ability\AbilityCheckInterface;
use Drupal\user_ability\AbilityContextInterface;
use Drupal\user_ability\NullContext;

final class ViewReportsCheck implements AbilityCheckInterface {

  public static function getContextClass(): string {
    return NullContext::class;
  }

  public static function getBusinessRule(): TranslatableMarkup {
    return new TranslatableMarkup('Anyone allowed to access site reports can view reports.');
  }

  public function abilityAccess(UserInterface $user, AbilityContextInterface $context): AccessResultInterface {
    return AccessResult::allowedIfHasPermission($user, 'access site reports')
      ->addCacheableDependency($user);
  }

}
```

A service tagged `ability_check`, whose `ability` attribute is the fully-qualified case, with no leading backslash:

```yaml
# my_module/my_module.services.yml
services:
  my_module.ability_check.view_reports:
    class: Drupal\my_module\AbilityCheck\ViewReportsCheck
    tags:
      -
        name: ability_check
        ability: 'Drupal\my_module\Enum\SiteAbility::ViewReports'
```

Rebuild the container (`drush cr`) and ask the question anywhere:

```php
use Drupal\my_module\Enum\SiteAbility;

$result = \Drupal::service('user_ability.checker')
  ->check(SiteAbility::ViewReports, \Drupal::currentUser());

$result->isAllowed();
```

For a user with the `access site reports` permission, `isAllowed()` returns `TRUE`; for everyone else it returns `FALSE`. `$result` carries the permission cache context, so it can go straight into a render array or a route access callback.

## Requirements

- PHP 8.1 or newer. Abilities are PHP enums, and the module uses `readonly` properties.
- Drupal 9 or 10 (`core_version_requirement: ^9 || ^10`).

## Installation

The module is not on Packagist or drupal.org, so install it from its git repository.

**With Composer.** Add the repository to your project's `composer.json`, then require the development branch:

```bash
composer config repositories.user_ability vcs git@github.com:aklump/drupal_user_ability.git
composer require aklump_drupal/user_ability:dev-main
drush en user_ability
```

The package type is `drupal-custom-module`, so a project built from `drupal/recommended-project` places it under `web/modules/custom/user_ability`.

**By hand.** Clone it into your custom modules directory and enable it:

```bash
git clone git@github.com:aklump/drupal_user_ability.git web/modules/custom/user_ability
drush en user_ability
```

Enabling it registers the `user_ability.checker` service and a `user_ability` logger channel. It adds no configuration, permissions, routes or database tables.

## Usage

### The four pieces

- **Ability** (`AbilityInterface`): an enum naming a business capability. Name cases as verbs or verb phrases (`PublishArticle`, `DeleteComment`), never as a role or a permission. The interface requires `\JsonSerializable`, because `json_encode()` cannot serialize a pure enum and silently returns `FALSE` for the whole payload that contains one. A backed enum is allowed but not required.
- **Context** (`AbilityContextInterface`): a value object carrying whatever a check needs beyond the account: a node, a group, a status. It is an empty marker interface; you define one class per shape.
- **Check** (`AbilityCheckInterface`): one service per ability that composes permissions, roles, ownership and anything else into one `AccessResultInterface`, with its cache metadata.
- **Checker** (`AbilityChecker`, service `user_ability.checker`): the dispatcher. A compiler pass collects every `ability_check`-tagged service at container-build time and registers it by its `ability` attribute.

### Checks that need a context

Define a context class for the data the check needs, and declare it from `getContextClass()`:

```php
namespace Drupal\my_module\Context;

use Drupal\node\NodeInterface;
use Drupal\user_ability\AbilityContextInterface;

final class NodeContext implements AbilityContextInterface {

  public function __construct(
    public readonly NodeInterface $node,
  ) {}

}
```

```php
namespace Drupal\my_module\AbilityCheck;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\my_module\Context\NodeContext;
use Drupal\user\UserInterface;
use Drupal\user_ability\AbilityCheckInterface;
use Drupal\user_ability\AbilityContextInterface;

final class PublishArticleCheck implements AbilityCheckInterface {

  public static function getContextClass(): string {
    return NodeContext::class;
  }

  public static function getBusinessRule(): TranslatableMarkup {
    return new TranslatableMarkup('Editors can publish any article; authors can publish their own.');
  }

  public function abilityAccess(UserInterface $user, AbilityContextInterface $context): AccessResultInterface {
    $result = AccessResult::allowedIf($user->hasPermission('administer nodes') || $user->hasPermission('publish article content'))
      ->addCacheableDependency($user)
      ->cachePerPermissions()
      ->addCacheableDependency($context->node);

    $is_author = (int) $context->node->getOwnerId() === (int) $user->id();
    return $result->orIf(
      AccessResult::allowedIf($is_author && $user->hasPermission('publish own article content'))
        ->addCacheableDependency($user)
        ->addCacheableDependency($context->node)
        ->cachePerPermissions()
    );
  }

}
```

Pass the context as the third argument:

```php
use Drupal\my_module\Context\NodeContext;
use Drupal\my_module\Enum\SiteAbility;

$can_publish = \Drupal::service('user_ability.checker')
  ->check(SiteAbility::PublishArticle, \Drupal::currentUser(), new NodeContext($node))
  ->isAllowed();
```

The checker enforces the declared class before it calls the check, so `abilityAccess()` never has to validate the shape of `$context`. When you pass no context, the checker substitutes `NullContext`, so a check never receives `NULL`.

### Cache metadata

A check's result must carry its cache metadata: at least `->addCacheableDependency($user)`, plus `->cachePerPermissions()` when a permission is involved, `->addCacheableDependency()` for every context entity it reads, and `->setCacheMaxAge()` for time-based rules. `addCacheableDependency($user)` adds no cache tags for the anonymous user, so a check whose answer can vary for anonymous visitors must also call `->cachePerPermissions()` or `->cachePerUser()`.

### A `can()` / `abilityTo()` facade

If your site wraps accounts in its own object, `AbilityAwareTrait` gives it `can()` (a boolean) and `abilityTo()` (the cache-aware `AccessResultInterface`). You supply the checker and the account:

```php
use Drupal\Core\Session\AccountInterface;
use Drupal\user_ability\AbilityAwareTrait;
use Drupal\user_ability\AbilityChecker;

class MyWrappedUser {
  use AbilityAwareTrait;

  public function __construct(
    private readonly AccountInterface $account,
    private readonly AbilityChecker $abilityChecker,
  ) {}

  protected function getAbilityChecker(): AbilityChecker {
    return $this->abilityChecker;
  }

  protected function getAbilityAccount(): AccountInterface {
    return $this->account;
  }
}

// $user->can(SiteAbility::ViewReports) returns TRUE or FALSE.
// $user->abilityTo(SiteAbility::PublishArticle, new NodeContext($node)) returns the result with its cache metadata.
```

Use `abilityTo()` for anything that renders; `can()` discards the cache metadata.

### When something is wrong

| Situation                                                                                         | What happens                                                                                    |
|---------------------------------------------------------------------------------------------------|-------------------------------------------------------------------------------------------------|
| No check is registered for the ability                                                            | `check()` returns `AccessResult::forbidden()` and logs a warning to the `user_ability` channel. |
| The account no longer resolves to a user (deleted mid-request)                                    | `check()` returns `AccessResult::forbidden()` and logs a warning.                               |
| The context is not an instance of the check's `getContextClass()`                                 | `check()` throws `\InvalidArgumentException`.                                                   |
| A tagged service has no `ability` attribute, names an undefined case, or starts the case with `\` | The container build fails with a `\LogicException` naming the service.                          |

### Running the tests

The unit tests live in `tests/src/Unit/AbilityCheckerTest.php` and extend Drupal's `UnitTestCase`, so run them from a Drupal site that has the module installed:

```bash
vendor/bin/phpunit -c web/core web/modules/custom/user_ability/tests
```
