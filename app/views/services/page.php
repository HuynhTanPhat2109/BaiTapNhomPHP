<?php
// The role area uses the same service content without the Admin layout/Sidebar.
if (!in_array($servicePage, ['index', 'form', 'show'], true)) throw new RuntimeException('Invalid service template.');
require dirname(__DIR__) . '/admin/dich-vu/' . $servicePage . '.php';
