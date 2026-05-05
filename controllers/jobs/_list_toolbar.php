<div data-control="toolbar loader-container">
    <a
        href="<?= Backend::url('palpalych/aitranslator/jobs/create') ?>"
        class="btn btn-primary">
        <i class="icon-plus"></i>
        <?= __("New :name", ['name' => 'Job']) ?>
    </a>

    <div class="toolbar-divider"></div>

    <button
        class="btn btn-secondary"
        data-request="onDelete"
        data-request-message="<?= __("Deleting...") ?>"
        data-request-confirm="<?= __("Are you sure?") ?>"
        data-list-checked-trigger
        data-list-checked-request
        disabled>
        <i class="icon-delete"></i>
        <?= __("Delete") ?>
    </button>

    <button
        class="btn btn-success"
        data-request="onBulkApprove"
        data-request-message="<?= __("Approving...") ?>"
        data-request-confirm="<?= __("Approve selected jobs?") ?>"
        data-list-checked-trigger
        data-list-checked-request
        disabled>
        <i class="icon-check"></i>
        <?= __("Approve") ?> <span data-list-checked-counter></span>
    </button>

    <button
        class="btn btn-danger"
        data-request="onBulkReject"
        data-request-message="<?= __("Rejecting...") ?>"
        data-request-confirm="<?= __("Reject selected jobs?") ?>"
        data-list-checked-trigger
        data-list-checked-request
        disabled>
        <i class="icon-ban"></i>
        <?= __("Reject") ?> <span data-list-checked-counter></span>
    </button>
</div>
