@include('errors.page', [
    'status' => '403',
    'title' => 'Access denied',
    'message' => 'You do not have permission to view this page.',
])
