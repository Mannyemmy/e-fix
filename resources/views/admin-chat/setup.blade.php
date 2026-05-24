<x-master-layout>
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-12">
                <div class="card card-block card-stretch">
                    <div class="card-body p-0">
                        <div class="d-flex justify-content-between align-items-center p-3 flex-wrap gap-3">
                            <h5 class="font-weight-bold">{{ $pageTitle ?? 'Live Chat Setup' }}</h5>
                            <a href="{{ route('admin-chat.index') }}" class="btn btn-sm btn-secondary">
                                <i class="fas fa-arrow-left"></i> Back
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-8 mx-auto">
                <div class="card">
                    <div class="card-body">
                        <h5 class="font-weight-bold mb-4">Configure Admin Chat User</h5>

                        <form action="{{ route('admin-chat.setup.save') }}" method="POST">
                            @csrf

                            <div class="form-group">
                                <label>Admin Firebase Chat UID <span class="text-danger">*</span></label>
                                <input type="text" name="admin_chat_uid" class="form-control" value="{{ old('admin_chat_uid', $adminUid) }}" required placeholder="e.g., abc123def456...">
                                <small class="form-text text-muted">
                                    The Firebase Auth UID that will represent the admin in the chat system.
                                    This must match the UID used by the mobile app when users contact support.
                                </small>
                            </div>

                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i>
                                <strong>How to get the Admin Firebase UID:</strong>
                                <ol class="mb-0 mt-2">
                                    <li>Go to Firebase Console → Authentication → Users</li>
                                    <li>Create a new user or use an existing one for admin chat</li>
                                    <li>Copy the User UID (listed in the users table)</li>
                                    <li>Paste it in the field above and save</li>
                                </ol>
                            </div>

                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Configuration
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-master-layout>
