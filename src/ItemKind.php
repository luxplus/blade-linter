<?php

namespace Luxplus\BladeLinter;

enum ItemKind
{
    case Scope;
    case Leaf;
    case TextRun;
    case Comment;
}
