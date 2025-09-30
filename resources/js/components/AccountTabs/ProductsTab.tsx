import React, { useState, useEffect } from 'react';
import { ApiService } from '../../services/ApiService';

const ProductsTab: React.FC = () => {
  const [products, setProducts] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    loadProducts();
  }, []);

  const loadProducts = async () => {
    try {
      setLoading(true);
      // Assuming products API exists
      const response = await ApiService.get('/api/product');
      if (response.data?.success) {
        setProducts(response.data.data?.data || []);
      }
    } catch (err: any) {
      console.error('Failed to load products:', err);
    } finally {
      setLoading(false);
    }
  };

  if (loading) {
    return (
      <div className="products-loading">
        <div className="loading-spinner"></div>
        <p>Loading your products...</p>
      </div>
    );
  }

  return (
    <div className="products-tab">
      <div className="products-header">
        <h2 className="products-title">
          <span className="title-icon">🛍️</span>
          My Products
        </h2>
        <button className="add-product-button">
          <span className="add-icon">➕</span>
          Add Product
        </button>
      </div>

      {products.length > 0 ? (
        <div className="products-grid">
          {products.map((product: any) => (
            <div key={product.id} className="product-item">
              <div className="product-image">
                {product.thumbnail ? (
                  <img src={product.thumbnail} alt={product.name} />
                ) : (
                  <div className="product-placeholder">📦</div>
                )}
              </div>
              <div className="product-details">
                <h3 className="product-name">{product.name}</h3>
                <p className="product-description">{product.description}</p>
                <div className="product-price">${product.price}</div>
                <div className="product-actions">
                  <button className="edit-button">✏️ Edit</button>
                  <button className="delete-button">🗑️ Delete</button>
                </div>
              </div>
            </div>
          ))}
        </div>
      ) : (
        <div className="empty-products">
          <div className="empty-icon">🛍️</div>
          <h3>No products yet</h3>
          <p>Create your first product to start selling</p>
          <button className="create-product-button">
            Create Product
          </button>
        </div>
      )}

      <style jsx>{`
        .products-tab {
          height: 100%;
          overflow-y: auto;
        }

        .products-header {
          display: flex;
          justify-content: space-between;
          align-items: center;

        }

        .products-title {
          display: flex;
          align-items: center;
          gap: 0.75rem;
          font-size: 2rem;
          font-weight: 700;
          margin: 0;
          color: #ffffff;
        }

        .title-icon {
          font-size: 1.75rem;
        }

        .add-product-button {
          display: flex;
          align-items: center;
          gap: 0.5rem;
          background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
          border: none;
          color: white;
          padding: 0.75rem 1.5rem;
          border-radius: 8px;
          cursor: pointer;
          font-size: 1rem;
          font-weight: 600;
          transition: transform 0.2s ease;
        }

        .add-product-button:hover {
          transform: translateY(-2px);
        }

        .add-icon {
          font-size: 1rem;
        }

        .products-grid {
          display: grid;
          grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
          gap: 1.5rem;
        }

        .product-item {
          background: rgba(255, 255, 255, 0.05);
          border: 1px solid rgba(255, 255, 255, 0.1);
          border-radius: 12px;
          overflow: hidden;
          transition: all 0.3s ease;
        }

        .product-item:hover {
          background: rgba(255, 255, 255, 0.1);
          transform: translateY(-4px);
        }

        .product-image {
          aspect-ratio: 16/9;
          overflow: hidden;
          background: rgba(255, 255, 255, 0.1);
          display: flex;
          align-items: center;
          justify-content: center;
        }

        .product-image img {
          width: 100%;
          height: 100%;
          object-fit: cover;
        }

        .product-placeholder {
          font-size: 3rem;
          opacity: 0.5;
        }

        .product-details {
          padding: 1.5rem;
        }

        .product-name {
          font-size: 1.25rem;
          font-weight: 600;
          margin: 0 0 0.5rem 0;
          color: #ffffff;
        }

        .product-description {
          color: rgba(255, 255, 255, 0.7);
          margin: 0 0 1rem 0;
          font-size: 0.9rem;
          line-height: 1.4;
        }

        .product-price {
          font-size: 1.5rem;
          font-weight: 700;
          color: #4ade80;
          margin-bottom: 1rem;
        }

        .product-actions {
          display: flex;
          gap: 0.5rem;
        }

        .edit-button {
          background: rgba(102, 126, 234, 0.8);
          border: none;
          color: white;
          padding: 0.5rem 1rem;
          border-radius: 6px;
          cursor: pointer;
          font-size: 0.85rem;
          transition: all 0.2s ease;
        }

        .edit-button:hover {
          background: rgba(102, 126, 234, 1);
        }

        .delete-button {
          background: rgba(255, 107, 107, 0.8);
          border: none;
          color: white;
          padding: 0.5rem 1rem;
          border-radius: 6px;
          cursor: pointer;
          font-size: 0.85rem;
          transition: all 0.2s ease;
        }

        .delete-button:hover {
          background: rgba(255, 107, 107, 1);
        }

        .empty-products {
          display: flex;
          flex-direction: column;
          align-items: center;
          justify-content: center;
          text-align: center;
          min-height: 400px;
          padding: 2rem;
        }

        .empty-icon {
          font-size: 4rem;
          margin-bottom: 1rem;
          opacity: 0.5;
        }

        .empty-products h3 {
          font-size: 1.5rem;
          margin: 0 0 1rem 0;
          color: #ffffff;
        }

        .empty-products p {
          color: rgba(255, 255, 255, 0.7);
          margin: 0 0 2rem 0;
          font-size: 1.1rem;
        }

        .create-product-button {
          background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
          border: none;
          color: white;
          padding: 1rem 2rem;
          border-radius: 8px;
          cursor: pointer;
          font-size: 1rem;
          font-weight: 600;
          transition: transform 0.2s ease;
        }

        .create-product-button:hover {
          transform: translateY(-2px);
        }

        .products-loading {
          display: flex;
          flex-direction: column;
          align-items: center;
          justify-content: center;
          height: 300px;
          text-align: center;
        }

        .loading-spinner {
          width: 50px;
          height: 50px;
          border: 3px solid rgba(255, 255, 255, 0.3);
          border-top: 3px solid #ffffff;
          border-radius: 50%;
          animation: spin 1s linear infinite;
          margin-bottom: 1rem;
        }

        @keyframes spin {
          0% { transform: rotate(0deg); }
          100% { transform: rotate(360deg); }
        }

        @media (max-width: 768px) {
          .products-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 1rem;
          }

          .products-title {
            font-size: 1.5rem;
          }

          .products-grid {
            grid-template-columns: 1fr;
          }
        }
      `}</style>
    </div>
  );
};

export default ProductsTab;