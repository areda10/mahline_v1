@extends('layouts.admin')

@section('title', 'Catalogues')

@section('content')
<div class="page-header">
    <div>
        <h1>Catalogues</h1>
        <p class="muted">Les catalogues de {{ $cooperative->name }}.</p>
    </div>

    @can('create', [\App\Domains\Catalog\Models\Catalog::class, (string) $cooperative->getKey()])
        <a class="button" href="{{ route('backoffice.catalogs.create', ['cooperative' => $cooperative->getKey()]) }}">
            Créer un catalogue
        </a>
    @endcan
</div>

<div class="card">
    @if($catalogs->isEmpty())
        <div class="empty">
            Aucun catalogue pour le moment.
        </div>
    @else
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Slug</th>
                        <th>Produits</th>
                        <th>État</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($catalogs as $catalog)
                        <tr>
                            <td>
                                <strong>{{ $catalog->name }}</strong>
                                @if($catalog->description)
                                    <div class="muted">{{ $catalog->description }}</div>
                                @endif
                            </td>
                            <td>{{ $catalog->slug }}</td>
                            <td>{{ $catalog->products()->count() }}</td>
                            <td>
                                <span class="badge {{ $catalog->is_active ? '' : 'badge-inactive' }}">
                                    {{ $catalog->is_active ? 'Actif' : 'Inactif' }}
                                </span>
                            </td>
                            <td>
                                <div class="actions">
                                    @can('view', $catalog)
                                        <a class="button button-secondary button-small"
                                           href="{{ route('backoffice.catalogs.show', ['cooperative' => $cooperative->getKey(), 'catalog' => $catalog->getKey()]) }}">
                                            Voir
                                        </a>
                                    @endcan

                                    @can('update', $catalog)
                                        <a class="button button-secondary button-small"
                                           href="{{ route('backoffice.catalogs.edit', ['cooperative' => $cooperative->getKey(), 'catalog' => $catalog->getKey()]) }}">
                                            Modifier
                                        </a>
                                    @endcan

                                    @can('delete', $catalog)
                                        <form class="inline-form"
                                              method="POST"
                                              action="{{ route('backoffice.catalogs.destroy', ['cooperative' => $cooperative->getKey(), 'catalog' => $catalog->getKey()]) }}"
                                              onsubmit="return confirm('Supprimer ce catalogue ? Les produits seront conservés.');">
                                            @csrf
                                            @method('DELETE')
                                            <button class="button button-danger button-small" type="submit">
                                                Supprimer
                                            </button>
                                        </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="pagination">{{ $catalogs->links() }}</div>
    @endif
</div>
@endsection