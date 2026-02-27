@extends($layout)

@section('content')
<x-global::pageheader :icon="'fa fa-list'">
    <h5>{{ __('label.overview') }}</h5>
    <h1>Non Visual Desktop</h1>
</x-global::pageheader>

<div class="maincontent">
    {!! $tpl->displayNotification() !!}

    <div class="maincontentinner">
        <h4 class="widgettitle title-light">Assigned Projects (All Together)</h4>
        @if (count($projects) === 0)
            <p>No assigned projects found.</p>
        @else
            <table class="table table-bordered">
                <thead>
                <tr>
                    <th>Project</th>
                    <th>Client</th>
                    <th>Open Tasks</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($projects as $project)
                    <tr>
                        <td>
                            <a href="{{ BASE_URL }}/projects/showProject/{{ $project['id'] }}">
                                {{ $project['name'] }}
                            </a>
                        </td>
                        <td>{{ $project['clientName'] ?? '-' }}</td>
                        <td>{{ $project['numberOfOpenTickets'] ?? '-' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif

        <br />

        <h4 class="widgettitle title-light">My Open To-Dos (Easy Browse)</h4>
        @if (count($tickets) === 0)
            <p>No open to-dos assigned.</p>
        @else
            <table class="table table-bordered">
                <thead>
                <tr>
                    <th>To-Do</th>
                    <th>Project</th>
                    <th>Due</th>
                    <th>Status</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($tickets as $ticket)
                    @php
                        $status = $ticket['status'] ?? '';
                                $statusName = (is_array($statusLabels[$status] ?? null) && isset($statusLabels[$status]['name']))
                                    ? $statusLabels[$status]['name']
                                    : ($status !== '' ? $status : 'Unknown');
                        $dueDate = $ticket['dateToFinish'] ?? '';
                        if ($dueDate === '' || $dueDate === '0000-00-00' || $dueDate === '1969-12-31 00:00:00') {
                            $dueLabel = __('text.anytime');
                        } else {
                                    try {
                                        $dueLabel = format($dueDate)->date(__('text.anytime'));
                                    } catch (\Throwable $e) {
                                        $dueLabel = __('text.anytime');
                                    }
                        }
                    @endphp
                    <tr>
                        <td>
                            <a href="{{ BASE_URL }}/tickets/showTicket/{{ $ticket['id'] }}">
                                {{ $ticket['headline'] ?? ('#'.$ticket['id']) }}
                            </a>
                        </td>
                        <td>{{ $ticket['projectName'] ?? '-' }}</td>
                        <td>{{ $dueLabel }}</td>
                        <td>{{ $statusName }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
@endsection
