"""Add configuration table and update document model

Revision ID: b3c4d5e6f7g8
Revises: a2b5c3d4e5f6
Create Date: 2025-01-20 10:00:00.000000

"""
from alembic import op
import sqlalchemy as sa


# revision identifiers, used by Alembic.
revision = 'b3c4d5e6f7g8'
down_revision = 'a2b5c3d4e5f6'
branch_labels = None
depends_on = None


def upgrade() -> None:
    # Create configurations table
    op.create_table('configurations',
        sa.Column('id', sa.Integer(), nullable=False),
        sa.Column('config_key', sa.String(length=255), nullable=False),
        sa.Column('config_value', sa.Text(), nullable=False),
        sa.Column('description', sa.Text(), nullable=True),
        sa.Column('data_type', sa.String(length=50), nullable=False),
        sa.Column('is_active', sa.Boolean(), nullable=False),
        sa.Column('created_at', sa.DateTime(timezone=True), server_default=sa.text('now()'), nullable=False),
        sa.Column('updated_at', sa.DateTime(timezone=True), server_default=sa.text('now()'), nullable=False),
        sa.PrimaryKeyConstraint('id'),
        sa.UniqueConstraint('config_key')
    )
    
    # Insert default configuration values
    op.execute("""
        INSERT INTO configurations (config_key, config_value, description, data_type, is_active) VALUES
        ('max_file_size_bytes', '52428800', 'Maximum file size allowed for document upload in bytes (50MB)', 'integer', true),
        ('max_documents_per_user', '10', 'Maximum number of documents a user can store', 'integer', true),
        ('minio_bucket_name', 'documents', 'MinIO bucket name for document storage', 'string', true),
        ('chromadb_collection_name', 'document_embeddings', 'ChromaDB collection name for document embeddings', 'string', true)
    """)


def downgrade() -> None:    
    # Drop configurations table
    op.drop_table('configurations')