<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Accept invitation</title></head>
<body><h1>Accept invitation</h1>
<p>Create your <?php echo e($invitation->role); ?> account for <?php echo e($invitation->email); ?>.</p>
<?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><p role="alert"><?php echo e($error); ?></p><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
<form method="POST" action="<?php echo e(route('invitations.accept', $token)); ?>"><?php echo csrf_field(); ?>
<p><label>Name <input name="name" value="<?php echo e(old('name')); ?>" required maxlength="255"></label></p>
<p><label>Password <input type="password" name="password" minlength="8" autocomplete="new-password" required></label></p>
<p><label>Confirm password <input type="password" name="password_confirmation" minlength="8" autocomplete="new-password" required></label></p>
<button type="submit">Create account</button>
</form></body></html>
<?php /**PATH C:\Users\Rahul Kumar\Downloads\sembark_tech\sembark_tech\resources\views/invitations/accept.blade.php ENDPATH**/ ?>