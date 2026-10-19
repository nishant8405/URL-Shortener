
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>URL Shortener - Sembark Tech</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 900px;
            margin: 40px auto;
            padding: 20px;
            background: #f3f4f6;
        }

        .card {
            background: white;
            padding: 24px;
            margin-bottom: 20px;
            border-radius: 8px;
        }

        input {
            box-sizing: border-box;
            width: 100%;
            padding: 12px;
            margin: 10px 0;
        }

        button {
            background: #2563eb;
            color: white;
            border: 0;
            padding: 12px 20px;
            cursor: pointer;
            border-radius: 5px;
        }

        .error { color: #dc2626; }
        .success { color: #15803d; }
        .url-item { padding: 15px 0; border-bottom: 1px solid #ddd; }
        a { overflow-wrap: anywhere; }
    </style>
</head>
<body>

    <h1>Sembark Tech URL Shortener</h1>
    <p><?php echo e(auth()->user()->name); ?> &middot; <?php echo e(auth()->user()->role->name); ?> &middot; <?php echo e(auth()->user()->company?->name ?? 'Platform'); ?></p>
    <p>Short links can be opened by anyone. Your role determines which URLs appear below.</p>

    <?php if(in_array(auth()->user()->role->name, ['SuperAdmin', 'Admin'])): ?>
        <p><a href="<?php echo e(route('invitations.index')); ?>">Invite users</a></p>
    <?php endif; ?>
    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('create', App\Models\ShortUrl::class)): ?>
    <div class="card">
        <h2>Create Short URL</h2>

        <?php if(session('success')): ?>
            <p class="success"><?php echo e(session('success')); ?></p>
        <?php endif; ?>

        <?php if($errors->any()): ?>
            <?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <p class="error"><?php echo e($error); ?></p>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        <?php endif; ?>

        <form method="POST" action="<?php echo e(route('short-urls.store')); ?>">
            <?php echo csrf_field(); ?>

            <label for="original_url">Original URL</label>
            <input
                type="url"
                id="original_url"
                name="original_url"
                placeholder="https://example.com"
                value="<?php echo e(old('original_url')); ?>"
                required
            >

            <button type="submit">Create Short URL</button>
        </form>
    </div>
    <?php else: ?>
        <p>Your role cannot create short URLs.</p>
    <?php endif; ?>

    <div class="card">
        <h2>Short URLs</h2>

        <?php $__empty_1 = true; $__currentLoopData = $urls; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $url): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <div class="url-item">
                <p><strong>Original URL:</strong> <?php echo e($url->original_url); ?></p>

                <p>
                    <strong>Short URL:</strong>
                    <a href="<?php echo e(route('short-urls.redirect', ['code' => $url->short_code])); ?>">
                        <?php echo e(route('short-urls.redirect', ['code' => $url->short_code])); ?>

                    </a>
                </p>
            </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <p>No URLs available to display.</p>
        <?php endif; ?>
        <nav aria-label="URL pages">
            <?php if($urls->previousPageUrl()): ?> <a href="<?php echo e($urls->previousPageUrl()); ?>">Previous</a> <?php endif; ?>
            <span>Page <?php echo e($urls->currentPage()); ?> of <?php echo e($urls->lastPage()); ?></span>
            <?php if($urls->nextPageUrl()): ?> <a href="<?php echo e($urls->nextPageUrl()); ?>">Next</a> <?php endif; ?>
        </nav>
    </div>

    <form method="POST" action="<?php echo e(url('/logout')); ?>">
        <?php echo csrf_field(); ?>
        <button type="submit">Logout</button>
    </form>

</body>
</html>
<?php /**PATH C:\Users\Rahul Kumar\Downloads\sembark_tech\sembark_tech\resources\views/short-urls/index.blade.php ENDPATH**/ ?>