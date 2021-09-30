# Doctrine POPO change tracker

By default, Doctrine ORM cannot compute changes made to an object (not an entity) belonging to an entity.

```php
use Doctrine\ORM\Mapping as ORM;

class State {
    public string $foo = 'bar';
}

#[ORM\Entity]
class Entity {
    // ...
    #[ORM\Column(type: 'json_document')]
    private State $state;
}

$entity->state->foo = 'baz';
$em->flush(); // will do nothing
```

## How to use this library

```php
use BenTools\DoctrinePopoTracker\TrackChanges;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Entity {
    // ...
    #[ORM\Column(type: 'json_document')]
    #[TrackChanges]
    private State $state;
}

$entity->state->foo = 'baz';
$em->flush(); // yay! ✓
```

## How it works

- It compares the object to a clone of this object - therefore your POPO should be cloneable.
- It uses the Reflection API to inject a clone of your object at flush time

## Installation

_TODO_

## Tests

_TODO_

## License

MIT.
