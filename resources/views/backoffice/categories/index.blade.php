@extends('layouts.admin')

@section('title', 'Catégories')

@section('content')
<div class="page-header">
    <div>
        <h1>Catégories</h1>
        <p class="muted">Catégories globales et catégories disponibles pour {{ $cooperative->name }}.</p>
    </div>

    @can('create', \App\Domains\Categories\Models\Category::class)
        <a class="button"
           href="{{ route('backoffice.categories.create', ['cooperative' => $cooperative->getKey()]) }}">
            Créer une catégorie
        </a>
    @endcan
</div>

<div class="card">
    @if($categories->isEmpty())
        <div class="empty">Aucune catégorie disponible.</div>
    @else
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Portée</th>
                        <th>Parent</th>
                        <th>État</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($categories as $category)
                        <tr>
                            <td>
                                <strong>{{ $category->name }}</strong>
                                @if($category->description)
                                    <div class="muted">{{ $category->description }}</div>
                                @endif
                            </td>
                            <td>
                                @if($category->isGlobal())
                                    <span class="badge">Globale MAHLINE</span>
                                @else
                                    <span class="badge">Locale</span>
                                @endif
                            </td>
                            <td>{{ $category->parent?->name ?? '—' }}</td>
                            <td>
                                <span class="badge {{ $category->is_active ? '' : 'badge-inactive' }}">
                                    {{ $category->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td>
                                <div class="actions">
                                    @can('update', $category)
                                        <a class="button button-secondary button-small"
                                           href="{{ route('backoffice.categories.edit', ['cooperative' => $cooperative->getKey(), 'category' => $category->getKey()]) }}">
                                            Modifier
                                        </a>
                                    @endcan

                                    @can('delete', $category)
                                        <form method="POST" class="inline-form"
                                              action="{{ route('backoffice.categories.destroy', ['cooperative' => $cooperative->getKey(), 'category' => $category->getKey()]) }}"
                                              onsubmit="return confirm('Supprimer cette catégorie et gérer sa branche selon les règles MAHLINE ?');">
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

        <div class="pagination">{{ $categories->links() }}</div>
    @endif
</div>
@endsection