"""add_structured_data_to_chat_message

Revision ID: d4e5f6a7b8c9
Revises: b3c4d5e6f7g8
Create Date: 2025-08-05 23:24:00.000000

"""
from alembic import op
import sqlalchemy as sa


# revision identifiers, used by Alembic.
revision = 'd4e5f6a7b8c9'
down_revision = 'b3c4d5e6f7g8'
branch_labels = None
depends_on = None


def upgrade():
    op.add_column('chat_messages', sa.Column('structured_data', sa.JSON(), nullable=True))


def downgrade():
    op.drop_column('chat_messages', 'structured_data')
