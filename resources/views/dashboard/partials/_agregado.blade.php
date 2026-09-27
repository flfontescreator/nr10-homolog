@if(count($rows) === 0)
    <div class="docs-empty">Sem NCs registradas ainda.</div>
@else
    <div class="table-wrap">
        <table class="grid">
            <thead>
                <tr>
                    <th>{{ $coluna }}</th>
                    <th class="text-right">NCs</th>
                    <th class="text-right">Concluídas</th>
                    <th class="text-right" style="width:120px">Conformidade</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $row)
                    <tr>
                        <td>{{ $row['nome'] }}</td>
                        <td class="text-right"><span class="badge badge-neutral">{{ $row['total'] }}</span></td>
                        <td class="text-right">{{ $row['concluidas'] }}</td>
                        <td class="text-right">
                            <div class="small">{{ number_format($row['percentual'], 0, ',', '.') }}%</div>
                            <div class="progress" style="margin-top:4px"><div class="progress-bar" style="width:{{ min($row['percentual'], 100) }}%"></div></div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
