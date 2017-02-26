using System;
using System.Collections.Generic;
using System.Linq;
using System.Text;
using System.Net;
using System.IO;
using System.Security.Cryptography;
using System.Threading;
using System.Windows.Forms;
using Microsoft.Win32;

namespace MafiaRatings.GameObjects
{
    public class Gate
    {
        private const char SEPARATOR = '#';
        private const string GATE_PAGE = "/gate.php";

        private string mHostUrl;
        private string mToken;
        private string mLastServerResponse;

        private int mUserId;
        private string mUserName;
        private int mUserFlags;
        private Club[] mClubs;
        private Club mClub;
        private ConnectionStatus mConnectionStatus = ConnectionStatus.Disconnected;
        private event ConnectionStatusChanged mOnConnectionStatusChange;
        private int mLang = Language.ENGLISH;

        private const string HOST_REGISTRY_KEY = "host";
#if DEBUG
        public const string DEFAULT_HOST_URL = "http://localhost";
#else
        public const string DEFAULT_HOST_URL = "http://mafiaratings.com";
#endif

        private byte[] Encode(string str)
        {
            System.Text.UTF8Encoding encoding = new System.Text.UTF8Encoding();
            byte[] bytes = encoding.GetBytes(str);
            byte[] key = encoding.GetBytes(Database.PasswordKey);
            if (key.Length > 0)
            {
                int key_index = 0;
                for (int i = 0; i < bytes.Length; ++i)
                {
                    bytes[i] = (byte)(bytes[i] ^ key[key_index]);
                    ++key_index;
                    if (key_index >= key.Length)
                    {
                        key_index = 0;
                    }
                }
            }
            return bytes;
        }

        private string Decode(byte[] bytes)
        {
            System.Text.UTF8Encoding encoding = new System.Text.UTF8Encoding();
            byte[] key = encoding.GetBytes(Database.PasswordKey);
            if (key.Length > 0)
            {
                int key_index = 0;
                for (int i = 0; i < bytes.Length; ++i)
                {
                    bytes[i] = (byte)(bytes[i] ^ key[key_index]);
                    ++key_index;
                    if (key_index >= key.Length)
                    {
                        key_index = 0;
                    }
                }
            }
            return encoding.GetString(bytes);
        }

        public enum ConnectionStatus
        {
            Connected,
            Disconnected,
            NotAvailable
        }

        public delegate void ConnectionStatusChanged(ConnectionStatus oldStatus);

        public class Request : DataWriter
        {
            private Gate mGate;
            private const string TOKEN_TAG = "[token]";

            public enum Code
            {
                Login = 0,
                Logout = 1,
                GetUserList = 2,
                RegisterUser = 6,
                CheckNewUserName = 7,
                RegisterNewUser = 8,

                DownloadData = 9,
                SubmitGame = 10,
            }

            public Request(Gate gate, string request) :
                base(SEPARATOR)
            {
                if (gate.Connected)
                {
                    request = request.Replace(TOKEN_TAG, gate.Token);
                }
                DataBuilder.Append(request);
                mGate = gate;
            }

            public Request(Gate gate, Code code) :
                base(SEPARATOR)
            {
                Write("data=");
                Add(code.ToString("d"));
                Add(gate.Lang);
                if (gate.Connected)
                {
                    Add(gate.Token);
                }
                else
                {
                    Add(TOKEN_TAG);
                }
                mGate = gate;
            }

            public Response Send()
            {
                return mGate.Send(Data);
            }
        }

        public class Response : DataReader
        {
            private Gate mGate;

            public const int Success = 0;
            public const int Error = 1;

            public Response(Gate gate, string data) :
                base(data, SEPARATOR)
            {
                mGate = gate;
                gate.Token = NextString();
                switch (NextInt())
                {
                    case Response.Success:
                        break;

                    case Response.Error:
                        throw new GameException(NextString());

                    default:
                        throw new GameException(Strings.ErrInvalidResponceCode, true);
                }
            }
        }

        public static string MD5(string str)
        {
            MD5 md5 = new MD5CryptoServiceProvider();
            str = BitConverter.ToString(md5.ComputeHash(ASCIIEncoding.Default.GetBytes(str))).ToLower();
            // remove dashes, php uses md5 without them
            return str.Replace("-", "");
        }

        private RegistryKey RegistryKey
        {
            get
            {
                string keyName = "Software\\" + Application.ProductName;
                RegistryKey key = Registry.CurrentUser.OpenSubKey(keyName, true);
                if (key == null)
                {
                    key = Registry.CurrentUser.CreateSubKey(keyName);
                }
                return key;
            }
        }

        public Gate()
        {
            try
            {
                mHostUrl = RegistryKey.GetValue(HOST_REGISTRY_KEY, DEFAULT_HOST_URL).ToString();
            }
            catch (Exception)
            {
                mHostUrl = DEFAULT_HOST_URL;
            }
        }

        public Gate.Response Send(string data)
        {
            return Send(data, false);
        }

        private Gate.Response Send(string data, bool loggingIn)
        {
            if (!loggingIn && mConnectionStatus != ConnectionStatus.Connected)
            {
                throw new GameException(Strings.ErrNotConnected, true);
            }

            try
            {
                HttpWebRequest request = null;
                Uri uri = new Uri(mHostUrl + GATE_PAGE);
                Encoding encoding = new UTF8Encoding(false);
                byte[] bytes = encoding.GetBytes(data);

                request = (HttpWebRequest)WebRequest.Create(uri);
                request.Method = "POST";
                request.ContentType = "application/x-www-form-urlencoded";
                request.ContentLength = bytes.Length;
                using (Stream writeStream = request.GetRequestStream())
                {
                   writeStream.Write(bytes, 0, bytes.Length);
                }

                mLastServerResponse = string.Empty;
                using (HttpWebResponse response = (HttpWebResponse)request.GetResponse())
                {
                    using (Stream responseStream = response.GetResponseStream())
                    {
                        using (StreamReader readStream = new StreamReader(responseStream, Encoding.UTF8))
                        {
                            mLastServerResponse = readStream.ReadToEnd();
                        }
                    }
                }
                return new Gate.Response(this, mLastServerResponse);
            }
            catch (GameException)
            {
                throw;
            }
            catch (Exception)
            {
                mConnectionStatus = ConnectionStatus.NotAvailable;
                mOnConnectionStatusChange(ConnectionStatus.Connected);
                throw new GameException(Strings.ErrNoConnection, true);
            }
        }

        private string LoginFilename
        {
            get
            {
                return Database.DataDir + MD5(mHostUrl.ToLower()) + ".lrq";
            }
        }

        private void Login(string request)
        {
            Logout();
            Gate.Response response = Send(request, true);
            mUserId = response.NextInt();
            mUserName = response.NextString();
            mUserFlags = response.NextInt();
            mClubs = new Club[response.NextInt()];
            for (int i = 0; i < mClubs.Length; ++i)
            {
                mClubs[i] = new Club(response, Database.VERSION);
            }
            mClub = mClubs[0];

            ConnectionStatus oldStatus = mConnectionStatus;
            mConnectionStatus = ConnectionStatus.Connected;
            mOnConnectionStatusChange(oldStatus);
        }

        public bool Login()
        {
            string request;
            try
            {
                request = Decode(File.ReadAllBytes(LoginFilename));
            }
            catch (Exception)
            {
                return false;
            }
            Login(request);
            return true;
        }

        public bool Login(string name, string password, bool savePassword)
        {
            string encodedPassword = MD5(password);

            Gate.Request request = new Gate.Request(this, Gate.Request.Code.Login);
            request.Add(Database.VERSION);
            request.Add(name);
            request.Add(encodedPassword);

            Login(request.Data);

            try
            {
                string fileName = LoginFilename;
                if (savePassword)
                {
                    try
                    {
                        File.SetAttributes(fileName, FileAttributes.Normal);
                    }
                    catch (Exception)
                    {
                    }
                    File.WriteAllBytes(fileName, Encode(request.Data));
                    File.SetAttributes(fileName, FileAttributes.Hidden);
                }
                else
                {
                    File.Delete(fileName);
                }
            }
            catch (Exception)
            {
                return false;
            }
            return true;
        }

        public void Logout()
        {
            if (mConnectionStatus == ConnectionStatus.Connected)
            {
                try
                {
                    string lastResponse = mLastServerResponse;
                    Gate.Request request = new Gate.Request(this, Gate.Request.Code.Logout);
                    request.Send();
                    mLastServerResponse = lastResponse;
                }
                catch (Exception)
                {
                }
                mToken = null;
                mUserId = 0;
                mUserName = null;
                mUserFlags = 0;
            }

            if (mConnectionStatus != ConnectionStatus.Disconnected)
            {
                mConnectionStatus = ConnectionStatus.Disconnected;
                mOnConnectionStatusChange(ConnectionStatus.Connected);
            }
        }

        public string HostUrl
        {
            get { return mHostUrl; }
            set
            {
                if (mHostUrl != value)
                {
                    Logout();
                    mHostUrl = value;
                    RegistryKey.SetValue(HOST_REGISTRY_KEY, value);
                }
            }
        }

        private static bool CheckToken(string token)
        {
            if (token == null)
            {
                return true;
            }
            if (token.Length != 32)
            {
                return false;
            }
            foreach (char c in token)
            {
                if (
                    (c < '0' || c > '9') &&
                    (c < 'a' || c > 'z') &&
                    (c < 'A' || c > 'Z'))
                {
                    return false;
                }
            }
            return true;
        }

        private string Token
        {
            get { return mToken; }
            set
            {
                if (value.Length == 0)
                {
                    mToken = null;
                }
                else
                {
                    if (!CheckToken(value))
                    {
                        throw new GameException(Strings.ErrInvalidResponse, true);
                    }
                    mToken = value;
                }
            }
        }

        public string LastServerResponse
        {
            get { return mLastServerResponse; }
        }

        public int UserId
        {
            get { return mUserId; }
        }

        public string UserName
        {
            get { return mUserName; }
        }

        public int UserFlags
        {
            get { return mUserFlags; }
        }

        public bool Connected
        {
            get { return mConnectionStatus == ConnectionStatus.Connected; }
        }

        public ConnectionStatus Status
        {
            get { return mConnectionStatus; }
        }

        public bool IsLoginSaved
        {
            get { return File.Exists(LoginFilename); }
        }

        public event ConnectionStatusChanged OnConnectionStatusChange
        {
            add
            {
                mOnConnectionStatusChange += value;
            }
            remove
            {
                mOnConnectionStatusChange -= value;
            }
        }

        public Club[] Clubs
        {
            get { return mClubs; }
        }

        public Club Club
        {
            get { return mClub; }
            set { mClub = value; }
        }

        public int Lang
        {
            get { return mLang; }
            set { mLang = value; }
        }
    }
}
