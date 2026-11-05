@extends('layouts.admin')

@section('title', 'Catégories globales MAHLINE')

@section('content')
<div class="page-header">
    <div><h1>Catégories globales MAHLINE</h1><p class="muted">Gestion centrale, sans sélection de coopérative.</p></div>
    <a class="button" href="{{ route('root.backoffice.categories.create') }}">Créer une catégorie globale</a>
</div>
<div class="card">
    @if($categories->isEmpty())
        <div class="empty">Aucune catégorie globale.</div>
    @else
        <div class="table-wrap"><table>
            <thead><tr><th>Nom</th><th>Parent</th><th>État</th><th>Actions</th></tr></thead>
            <tbody>
            @foreach($categories as $category)
                <tr>
                    <td><strong>{{ $category->name }}</strong>@if($category->description)<div class="muted">{{ $category->description }}</div>@endif</td>
                    <td>{{ $category->parent?->name ?? '—' }}</td>
                    <td><span class="badge {{ $category->is_active ? '' : 'badge-inactive' }}">{{ $category->is_active ? 'Active' : 'Inactive' }}</span></td>
                    <td><div class="actions">
                        <a class="button button-secondary button-small" href="{{ route('root.backoffice.categories.edit', $category) }}">Modifier</a>
                        <form method="POST" class="inline-form" action="{{ route('root.backoffice.categories.destroy', $category) }}" onsubmit="return confirm('Supprimer cette catégorie et sa branche globale ? Les catégories locales seront conservées.');">
                            @csrf @method('DELETE')
                            <button class="button button-danger button-small" type="submit">Supprimer</button>
                        </form>
                    </div></td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
        <div class="pagination">{{ $categories->links() }}</div>
    @endif
</div>
@endsection
