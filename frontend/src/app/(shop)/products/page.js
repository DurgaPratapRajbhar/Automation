import Link from 'next/link';
import Alert from '@/components/ui/Alert';
import PageHeader from '@/components/ui/PageHeader';
import DeleteProductButton from '@/features/shop/products/DeleteProductButton';
import ProductForm from '@/features/shop/products/ProductForm';
import { addProduct, updateProduct } from '@/features/shop/products/actions';
import { getProducts } from '@/features/shop/products/queries';
import { can } from '@/features/shop/workspace/queries';

export const metadata = { title: 'Products' };

export default async function ProductsPage({ searchParams }) {
  const { search, cursor } = await searchParams;
  const { ok, data } = await getProducts({ search, cursor });

  if (!ok) return <Alert message={data.message} />;

  const [canCreate, canEdit, canDelete] = await Promise.all([can('products.create'), can('products.edit'), can('products.delete')]);
  const nextPage = data.next_cursor && `/products?${new URLSearchParams({ ...(search && { search }), cursor: data.next_cursor })}`;

  return (
    <div className="space-y-4">
      <PageHeader title="Products" />

      <p className="max-w-2xl text-sm text-slate-600">
        What you sell, with its price and how many are left. The WhatsApp assistant tells customers the price and availability from this list, and never guesses.
      </p>

      <form className="flex gap-2">
        <input name="search" defaultValue={search} placeholder="Search a product" className="w-full max-w-xs rounded-lg border border-slate-300 bg-white px-3 py-2 outline-none focus:border-slate-900" />
        <button className="rounded-lg px-4 py-2 text-sm font-medium ring-1 ring-slate-300 hover:bg-slate-100">Search</button>
      </form>

      {canCreate && (
        <details className="rounded-xl bg-white p-4 ring-1 ring-slate-200">
          <summary className="cursor-pointer text-sm font-medium">Add a product</summary>
          <div className="mt-4"><ProductForm action={addProduct} resetOnSave /></div>
        </details>
      )}

      {data.data.length === 0 ? (
        <p className="text-slate-500">{search ? 'No product found.' : 'No products yet. Add what you sell.'}</p>
      ) : (
        <ul className="divide-y divide-slate-100 overflow-hidden rounded-xl bg-white ring-1 ring-slate-200">
          {data.data.map((product) => (
            <li key={product.id} className="px-4 py-3">
              <div className="flex items-center justify-between gap-3">
                <div className="min-w-0">
                  <p className="truncate font-medium">{product.name}</p>
                  <p className="text-sm text-slate-600">
                    ₹{Number(product.price).toLocaleString('en-IN')}{product.unit ? ` / ${product.unit}` : ''}
                    {product.stock !== null && ` · ${product.stock} left`}
                    {product.duration_minutes && ` · ${product.duration_minutes} min`}
                  </p>
                </div>
                <span className={`shrink-0 rounded-full px-2 py-0.5 text-xs font-semibold ${product.available ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-600'}`}>
                  {product.available ? 'Available' : product.active ? 'Out of stock' : 'Not selling'}
                </span>
              </div>

              {(canEdit || canDelete) && (
                <details className="mt-2">
                  <summary className="cursor-pointer text-xs font-medium text-slate-600">Edit</summary>
                  <div className="mt-3 space-y-3">
                    {canEdit && <ProductForm action={updateProduct.bind(null, product.id)} product={product} />}
                    {canDelete && <DeleteProductButton productId={product.id} name={product.name} />}
                  </div>
                </details>
              )}
            </li>
          ))}
        </ul>
      )}

      {nextPage && <Link href={nextPage} className="text-sm underline">More</Link>}
    </div>
  );
}
