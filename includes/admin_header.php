<?php
/*
 * Admin site header / nav component.
 * The including page sets $navActive (e.g. 'applicants') before including this
 * to highlight the current section. Included inside <body>, after the bootstrap.
 */
$active = $navActive ?? '';
?>
<header class="site-header">
    <div class="site-header-inner">
       <a class="brand" href="/niitproject/public/admin/dashboard.php">Bright House College</a>
<nav class="site-nav">
    <a href="/niitproject/public/admin/dashboard.php"<?= $active === 'dashboard' ? ' class="active"' : '' ?>>Dashboard</a>
    <a href="/niitproject/public/admin/applicants/index.php"<?= $active === 'applicants' ? ' class="active"' : '' ?>>Applicants</a>
    <a href="/niitproject/public/admin/teachers/index.php"<?= $active === 'teachers' ? ' class="active"' : '' ?>>Teachers</a>
    <a href="/niitproject/public/admin/subjects/index.php"<?= $active === 'subjects' ? ' class="active"' : '' ?>>Subjects</a>
    <a href="/niitproject/public/admin/announcements/index.php"<?= $active === 'announcements' ? ' class="active"' : '' ?>>Announcements</a>
    <a href="/niitproject/public/logout.php" class="logout">Log out</a>
</nav>
    </div>
</header>
