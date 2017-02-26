using System;
using System.Collections.Generic;
using System.ComponentModel;
using System.Drawing;
using System.Data;
using System.Linq;
using System.Text;
using System.Windows.Forms;

namespace MafiaRatings
{
    public partial class BorderComboBox : UserControl
    {
        public BorderComboBox()
        {
            InitializeComponent();
        }

        private void BorderCombobox_Resize(object sender, EventArgs e)
        {
            comboBox.Top = (ClientRectangle.Height - comboBox.Height) / 2;
        }

        public int SelectedIndex
        {
            get { return comboBox.SelectedIndex; }
            set { comboBox.SelectedIndex = value; }
        }

        public ComboBox.ObjectCollection Items
        {
            get { return comboBox.Items; }
        }

        public event EventHandler SelectedIndexChanged
        {
            add { comboBox.SelectedIndexChanged += value; }
            remove { comboBox.SelectedIndexChanged -= value; }
        }

        public object SelectedItem
        {
            get { return comboBox.SelectedItem; }
        }

        public bool IsMyEvent(object sender)
        {
            return comboBox == sender;
        }

        private void BorderComboBox_EnabledChanged(object sender, EventArgs e)
        {
            comboBox.Visible = Enabled;
        }
    }
}
