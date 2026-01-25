<?php
    use PalPalych\AiTranslator\Models\Job\JobStatus;

    // Get the model instance to check status
    $model = $this->formGetModel();
    $isReview = $model->status === JobStatus::review;
?>

<?php if (!$this->fatalError): ?>

<?php Block::put('form-contents') ?>
<div class="layout-row min-size">
    <?= $this->formRenderOutsideFields() ?>

    <div class="form-buttons">
        <div class="loading-indicator-container">

            <?php if ($isReview): ?>
                <!-- APPROVE BUTTON -->
                <button
                    type="button"
                    class="btn btn-success oc-icon-check"
                    data-request="onApprove"
                    data-load-indicator="Applying translation..."
                    data-hotkey="ctrl+s, cmd+s">
                    Approve & Apply
                </button>

                <!-- REJECT BUTTON -->
                <button
                    type="button"
                    class="btn btn-danger oc-icon-times"
                    data-request="onReject"
                    data-request-confirm="Are you sure you want to reject this translation?"
                    data-load-indicator="Rejecting...">
                    Reject
                </button>
            <?php else: ?>
                <!-- STANDARD BUTTONS (For other statuses) -->
                <button
                    type="submit"
                    data-request="onSave"
                    data-request-data="redirect:0"
                    data-hotkey="ctrl+s, cmd+s"
                    data-load-indicator="<?= e(trans('backend::lang.form.saving')) ?>"
                    class="btn btn-primary">
                    <?= e(trans('backend::lang.form.save')) ?>
                </button>
                <button
                    type="button"
                    class="btn btn-default"
                    data-request="onSave"
                    data-request-data="close:1"
                    data-hotkey="ctrl+enter, cmd+enter"
                    data-load-indicator="<?= e(trans('backend::lang.form.saving')) ?>">
                    <?= e(trans('backend::lang.form.save_and_close')) ?>
                </button>
            <?php endif; ?>

            <span class="btn-text">
                <?= e(trans('backend::lang.form.or')) ?>
                <a href="<?= Backend::url('palpalych/aitranslator/jobs') ?>">
                    <?= e(trans('backend::lang.form.cancel')) ?>
                </a>
            </span>

            <!-- Delete button always available -->
            <button
                type="button"
                class="oc-icon-trash-o btn-icon danger pull-right"
                data-request="onDelete"
                data-load-indicator="<?= e(trans('backend::lang.form.deleting')) ?>"
                data-request-confirm="<?= e(trans('backend::lang.form.delete_confirm')) ?>">
            </button>

        </div>
    </div>
</div>

<!-- ... Rest of the file remains the same ... -->
<div class="layout-row">
    <?= $this->formRenderPrimaryTabs() ?>
</div>
<?php Block::endPut() ?>

<?php Block::put('form-sidebar') ?>
<div class="hide-tabs"><?= $this->formRenderSecondaryTabs() ?></div>
<?php Block::endPut() ?>

<?php Block::put('body') ?>
<?= Form::open([
    'class'=>'layout stretch',
    'data-change-monitor' => 'true',
    'data-window-close-confirm' => e(trans('backend::lang.form.confirm_tab_close')),
    'id' => 'review-form'
]) ?>
<?= $this->makeLayout('form-with-sidebar') ?>
<?= Form::close() ?>
<?php Block::endPut() ?>

<?php else: ?>
    <!-- Error State -->
    <div class="control-breadcrumb">
        <?= Block::placeholder('breadcrumb') ?>
    </div>
    <div class="padded-container">
        <p class="flash-message static error"><?= e(trans($this->fatalError)) ?></p>
        <p><a href="<?= Backend::url('palpalych/aitranslator/jobs') ?>" class="btn btn-default"><?= e(trans('backend::lang.form.return_to_list')) ?></a></p>
    </div>
<?php endif ?>
