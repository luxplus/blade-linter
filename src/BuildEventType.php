<?php

namespace Luxplus\BladeLinter;

enum BuildEventType
{
    case Leaf;
    case TagOpen;
    case TagClose;
    case ScopeOpen;
    case ScopeBoundary;
    case ScopeClose;
}
